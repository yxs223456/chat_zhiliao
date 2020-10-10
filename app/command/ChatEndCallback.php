<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\Constant;
use app\common\enum\ChatTypeEnum;
use app\common\enum\UserIsPrettyEnum;
use app\common\enum\WalletAddEnum;
use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\common\model\UserWalletFlowModel;
use app\common\service\UserInfoService;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

/**
 * 通话结束后续处理
 */
class ChatEndCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $chatId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:ChatEndCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
            chatEndCallbackConsumer([$this, 'receive']);
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "chat_id" => $this->chatId,
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
        if (!is_array($msgArray) || empty($msgArray["chat_id"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->chatId = $msgArray["chat_id"];

        $this->doWork($msgArray, $message);
    }

    private function doWork(array $msgArray, AMQPMessage $message)
    {
        // 判断是否已处理
        $chatId = $msgArray["chat_id"];
        $is_callback_sign = Db::name("tmp_chat_end_callback")
            ->where("chat_id", $chatId)
            ->find();
        if (empty($is_callback_sign)) {
            throw new Exception("do already");
        }

        // 需要处理的通话
        $chat = Db::name("chat")->where("id", $chatId)->find();
        $redis = Redis::factory();

        Db::startTrans();
        try {
            // 通话产生费用时，接听方获取通话收益
            if ($is_callback_sign["s_u_pay"]) {
                // 通话分成比例，男、女神0.8，普通人0.5
                $tUInfo = UserInfoService::getUserInfoById($chat["t_u_id"], $redis);
                $dividedRate = $tUInfo["is_pretty"] == UserIsPrettyEnum::NO ?
                    Constant::ORDINARY_CHAT_DIVIDED_RATE : Constant::PRETTY_CHAT_DIVIDED_RATE;
                // 本次通话收入
                $income = (int) ceil($dividedRate * $is_callback_sign["s_u_pay"]);
                // 增加接听人钱包余额
                $tUWallet = Db::name("user_wallet")->where("u_id", $chat["t_u_id"])->find();
                Db::name("user_wallet")->where("id", $tUWallet["id"])
                    ->inc("income_amount", $income)
                    ->inc("income_total_amount", $income)
                    ->inc("total_balance", $income)
                    ->update();
                // 纪录接听人钱包流水
                UserWalletFlowModel::addFlow(
                    $chat["t_u_id"],
                    $income,
                    $chat["chat_type"] == ChatTypeEnum::VIDEO ?
                        WalletAddEnum::VIDEO_CHAT : WalletAddEnum::VOICE_CHAT,
                    $chatId,
                    $tUWallet["total_balance"],
                    $tUWallet["total_balance"] + $income
                );
            }

            /**
             * 删除临时表数据
             */
            Db::name("tmp_chat_end_callback")
                ->where("id", $is_callback_sign["id"])
                ->delete();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            $redis->close();
        }

    }
}