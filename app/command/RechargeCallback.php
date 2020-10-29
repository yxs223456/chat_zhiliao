<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-10
 * Time: 10:07
 */

namespace app\command;

use app\common\enum\ChatStatusEnum;
use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\gateway\GatewayClient;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 充值成功后续处理
 */
class RechargeCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $userId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:RechargeCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
//            rechargeCallbackConsumer([$this, 'receive']);

            $redis = Redis::factory();
            while(time() - $this->beginTime <= $this->maxAllowTime) {
                $data = rechargeCallbackConsumer($redis);
                if (isset($data["u_id"])) {
                    $this->checkChat($data["u_id"]);
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
            /**
             * 判断是否是聊天中续费
             */
            $this->checkChat($userId);

        } catch (\Throwable $e) {
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }

    private function checkChat($userId)
    {
        // 用户是否正在聊天中，且是聊天发起人
        $whereStatusStr = "status = ".ChatStatusEnum::CALLING;
        $chat = Db::name("chat")
            ->where("s_u_id = $userId and $whereStatusStr")
            ->find();
        if (empty($chat)) {
            return;
        }

        // 通话免费不用处理
        $price = $chat["t_user_price"];     // 通话价格
        $isFree = $price == 0 ? 1 : 0;      // 通话是否免费
        if ($isFree == 1) {
            return;
        }

        // 再次计算通话时长，通过长连接通知客户端
        $minutes = $chat["free_minutes"];   // 不免费时最大通话分钟数
        if (!$isFree) {
            // 拨打方剩余余额
            $wallet = Db::name("user_wallet")->where("u_id", $userId)->find();
            $balance = $wallet["total_balance"];
            $payMinutes = floor($balance/$price);
            $minutes += $payMinutes;
        }

        $data = [
            "chat_id" => $chat["id"],
            "is_free" => $isFree,
            "current_time" => time(),
            "deadline" => $chat["chat_begin_time"] + ($minutes * 60),
        ];
        $scene = "chat_time_notice";

        GatewayClient::sendToUid($userId, $scene, $data);
    }
}