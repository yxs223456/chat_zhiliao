<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

/**
 * 访问日志的后续处理
 */
class VisitorLogCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $userId = "";
    private $visitorId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:VisitorLogCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
            userVisitorCallBackConsumer([$this, 'receive']);
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "u_id" => $this->userId,
                "v_id" => $this->visitorId,
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "message" => $e->getMessage(),
            ];
            Log::write(json_encode($error), "error");
            $errorMessage = "";
            foreach ($error as $key=>$value) {
                $errorMessage .= "$key: " . $value . "\n";
            }
            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yangxiushan"]);
        }
    }

    public function receive(AMQPMessage $message)
    {
        //消息内容
        $msg = $message->getBody();

        //确保当前脚本不占用太多内存
        $usedMemory = memory_get_usage();
        if ($usedMemory >= $this->maxAllowMemory ||
            time() - $this->beginTime >= $this->maxAllowTime)
        {
            //拒绝消息，并把消息重新放回队列
            RabbitMQ::rejectMessage($message);
            exit;
        }

        //判断数据是否合法
        $msgArray = json_decode($msg, true);
        if (!is_array($msgArray) || empty($msgArray["uid"]) || empty($msgArray["vid"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->userId = $msgArray["uid"];
        $this->visitorId = $msgArray["vid"];

        $this->doWork($message);
    }

    private function doWork(AMQPMessage $message)
    {
        // 判断今天是否已访问过
        $redis = Redis::factory();
        $bool = getUserVisitorExists($this->userId, $this->visitorId, $redis);
        if ($bool) {
            RabbitMQ::ackMessage($message);
            return;
        }

        Db::startTrans();
        try {

            Db::name("visitor_log")
                ->insert([
                   'u_id' => $this->userId,
                    'visitor_u_id' => $this->visitorId,
                    'date' => date("Y-m-d"),
                ]);

            Db::name("visitor_count")
                ->where("u_id", $this->userId)
                ->inc("count", 1)
                ->update();

            Db::commit();
            // 添加今日访问缓存
            cacheUserVisitorIdData($this->userId, $this->visitorId, $redis);
            // 删除分页缓存
            deleteUserVisitorPageData($this->userId, $redis);
            // 删除总数缓存

        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }
}