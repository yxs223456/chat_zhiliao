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
use app\common\service\VisitorService;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
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
//            userVisitorCallBackConsumer([$this, 'receive']);

            $redis = Redis::factory();
            while (time() - $this->beginTime <= $this->maxAllowTime) {
                $data = userVisitorCallBackConsumer($redis);
                if (!empty($data["uid"]) && !empty($data["vid"])) {
                    $this->userId = $data["uid"];
                    $this->visitorId = $data["vid"];
                    $this->doWorkR();
                }
            }
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
            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yanglichao"]);
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
        // 判断是访问和被访问是否是同一个人，同一个人忽略
        if ($this->userId == $this->visitorId) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
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
            // 添加访问日志
            Db::name("visitor_log")
                ->insert([
                    'u_id' => $this->userId,
                    'visitor_u_id' => $this->visitorId,
                    'date' => date("Y-m-d"),
                ]);

            // 更新访问总次数（没有总数初始化，有更新数据）
            $exists = Db::name("visitor_count")->where("u_id", $this->userId)->find();
            if (empty($exists)) {
                Db::name("visitor_count")->insert(["u_id" => $this->userId, 'count' => 1]);
            } else {
                Db::name("visitor_count")
                    ->where("u_id", $this->userId)
                    ->inc("count", 1)
                    ->update();
            }

            Db::commit();
            // 添加今日访问缓存
            cacheUserVisitorIdData($this->userId, $this->visitorId, $redis);
            // 删除分页缓存
            deleteUserVisitorPageData($this->userId, $redis);
            // 更新总数缓存
            VisitorService::updateVisitorSumCount($this->userId, $redis);

        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }

    /**
     * redis 队列处理逻辑
     *
     * @throws \Throwable
     */
    private function doWorkR()
    {
        $redis = Redis::factory();
        // 访问的自己返回
        if ($this->userId == $this->visitorId) {
            return;
        }
        // 判断今天是否已访问过
        $bool = getUserVisitorExists($this->userId, $this->visitorId, $redis);
        if ($bool) {
            return;
        }

        Db::startTrans();
        try {
            // 添加访问日志
            Db::name("visitor_log")
                ->insert([
                    'u_id' => $this->userId,
                    'visitor_u_id' => $this->visitorId,
                    'date' => date("Y-m-d"),
                ]);

            // 更新访问总次数（没有总数初始化，有更新数据）
            $exists = Db::name("visitor_count")->where("u_id", $this->userId)->find();
            if (empty($exists)) {
                Db::name("visitor_count")->insert(["u_id" => $this->userId, 'count' => 1]);
            } else {
                Db::name("visitor_count")
                    ->where("u_id", $this->userId)
                    ->inc("count", 1)
                    ->update();
            }

            Db::commit();
            // 添加今日访问缓存
            cacheUserVisitorIdData($this->userId, $this->visitorId, $redis);
            // 删除分页缓存
            deleteUserVisitorPageData($this->userId, $redis);
            // 更新总数缓存
            VisitorService::updateVisitorSumCount($this->userId, $redis);

        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}