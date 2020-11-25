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
 * 用户注册后续处理
 */
class UserAddParentCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $userId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:UserAddParentCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
//            userAddParentCallbackConsumer([$this, 'receive']);

            $redis = Redis::factory();
            while(time() - $this->beginTime <= $this->maxAllowTime) {
                $data = userAddParentCallbackConsumer($redis);
                if (isset($data["u_id"])) {
                    $this->userId = $data["u_id"];
                    $this->addParentCallback($data["u_id"]);
                    $this->userId = "";
                }
            }
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "u_id" => $this->userId,
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
        if (!is_array($msgArray) || empty($msgArray["u_id"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->userId = $msgArray["u_id"];

        $this->doWork($msgArray, $message);
    }

    private function doWork(array $msgArray, AMQPMessage $message)
    {
        try {
            $userId = $msgArray["u_id"];
//            $this->addParentCallback($userId, $message);

        } catch (\Throwable $e) {
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }

    private function addParentCallback($userId)
    {
        // 判断是否已处理
        $is_callback_sign = Db::name("tmp_add_parent_callback")
            ->where("u_id", $userId)
            ->find();
        if (empty($is_callback_sign)) {
            throw new Exception("do already");
        }

        //用户全部辈分上级的下级数全部+1， 用户直接上级的直接下级数+1
        $userCommunity = Db::name("user_community")->where("u_id", $userId)->find();
        if (empty($userCommunity["p_id_path"])) {
//            RabbitMQ::ackMessage($message);
            return;
        }
        $userPIdPathArray = explode(",", $userCommunity["p_id_path"]);
        $directParentId = $userPIdPathArray[0];

        Db::startTrans();
        try {

            /**
             * 用户直接上级的直接下级数+1
             */
            Db::name("user_community")
                ->where("u_id", $directParentId)
                ->inc("s_direct_count", 1)
                ->update();

            /**
             * 用户全部辈分上级的下级数全部+1
             */
            Db::name("user_community")
                ->whereIn("u_id", $userPIdPathArray)
                ->inc("s_total_count", 1)
                ->update();

            /**
             * 删除临时表数据
             */
            Db::name("tmp_add_parent_callback")
                ->where("id", $is_callback_sign["id"])
                ->delete();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}