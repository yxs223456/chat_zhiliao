<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\Constant;
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
class UserRegisterCallback extends Command
{
    use CommandTrait;

    private $userId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('diamondStore:userAddParentStatistical');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->rabbitConsumer();
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

    private function rabbitConsumer()
    {
        $exchangeName = Constant::RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_callback";
        $queueName = Constant::RABBIT_MQ_QUEUE_PREFIX . "user_add_parent_statistical";
        $routingKey = "user_add_parent_statistical";

        RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, [$this, 'receive']);
    }

    public function receive(AMQPMessage $message)
    {
        //消息内容
        $msg = $message->getBody();

        //确保当前脚本不占用太多内存
        $usedMemory = memory_get_usage();
        if ($usedMemory >= $this->maxAllowMemory) {
            //拒绝消息，并把消息重新放回队列
            RabbitMQ::rejectMessage($message);
            return;
        }

        //判断数据是否合法
        $msgArray = json_decode($msg, true);
        if (!is_array($msgArray) || empty($msgArray["uuid"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->userUuid = $msgArray["uuid"];

        $this->doWork($msgArray, $message);
    }

    public function doWork(array $msgArray, AMQPMessage $message)
    {
        $user = Db::name("user_base")->where("uuid", $msgArray["uuid"])->find();
        if (empty($user)) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            throw new Exception("user not exists");
        }
        if (empty($user["p_uuid"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            throw new Exception("user have not parent");
        }

        $userParentUuidPath = $user["parent_uuid_path"];
        $insertAllData = [];
        $updateData = [];
        $deleteData = [
            [
                "table" => "tmp_user_add_parent_callback",
                "where" => "user_uuid = '" . $user["uuid"] . "'",
            ]
        ];

        Db::startTrans();
        try {

            $is_callback_sign = Db::name("tmp_user_add_parent_callback")
                ->where("user_uuid", $user["uuid"])
                ->lock(true)
                ->find();
            if (empty($is_callback_sign)) {
                //显示确认，队列接收到显示确认后会删除该消息
                RabbitMQ::ackMessage($message);
                throw new Exception("user parent stat already");
            }

            /**
             *  上下级关系统计
             */
            $this->step1($user, $userParentUuidPath, $insertAllData, $updateData);
            /**
             * 处理数据库写逻辑
             */
            $this->dbWrite($updateData, $insertAllData, $deleteData);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

        /**
         * 七日社群邀请下级数
         */
        $this->statSevenDayNewDescendantCount($user["p_uuid"], $userParentUuidPath);
    }

    public function step1($user, $parentUuidPath, &$insertAllData, &$updateData)
    {
        $parentUuids = explode(",", $parentUuidPath);
        $parentUuidsAsc = array_reverse($parentUuids);
        foreach ($parentUuidsAsc as $rank => $parentUuid) {
            $descendantRank = $rank + 1;
            $insertAllData["user_descendant_access"][] = [
                "uuid" => "uda" . date("ymdhis") . getRandomString(15),
                "user_uuid" => $parentUuid,
                "descendant_uuid" => $user["uuid"],
                "descendant_level" => $user["user_level"],
                "descendant_rank" => $descendantRank,
                "unlock_diamond_coin_reward" => 0,
                "create_time" => time(),
                "update_time" => time(),
            ];
            if ($descendantRank == 1) {
                $updateData[] = [
                    "table" => "user_descendant",
                    "where" => "user_uuid = '" . $parentUuid . "'",
                    "updateFields" => [
                        "update_time" => time(),
                    ],
                    "inc" => [
                        "total_invite_count" => 1,
                        "direct_invite_count" => 1,
                    ],
                ];

            } else {
                $updateData[] = [
                    "table" => "user_descendant",
                    "where" => "user_uuid = '" . $parentUuid . "'",
                    "updateFields" => [
                        "update_time" => time(),
                    ],
                    "inc" => [
                        "total_invite_count" => 1,
                    ],
                ];
            }
        }
    }

    public function statSevenDayNewDescendantCount($directParentUuid, $parentUuidPath)
    {
        $redis = Redis::factory();

        try {
            $parentUuids = explode(",", $parentUuidPath);

            $sevenDayDateRanges = [];
            $now = time();
            for ($i = 6; $i >= 0 ; $i--) {
                $sevenDayDateRanges[] = date("Y-m-d", $now-($i*86400)) . "_" .
                    date("Y-m-d", $now-(($i-6)*86400));

            }

            foreach ($parentUuids as $parentUuid) {
                foreach ($sevenDayDateRanges as $sevenDayDateRange) {
                    zIncrSevenDayNewDescendantCount($parentUuid, $directParentUuid, $sevenDayDateRange, $redis);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $redis->close();
        }
    }
}