<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-04
 * Time: 17:10
 */

namespace app\command;

use app\common\enum\ChatStatusEnum;
use app\common\helper\Redis;
use app\common\helper\ShengWang;
use app\common\helper\WeChatWork;
use app\common\service\ChatService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 通话结束后续处理
 */
class ChatStatusCheck2 extends Command
{
    use CommandTrait;

    private $beginTime;
    private $chatId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:ChatStatusCheck2');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();

            $redis = Redis::factory();
            while(time() - $this->beginTime <= $this->maxAllowTime) {
                $data = chatStatusCheckConsumer($redis);
                if (isset($data["chat_id"])) {
                    $chatId = $data["chat_id"];
                    $this->doWork($chatId);
                }
            }
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

    private function doWork($chatId)
    {
        $chatInfo = ShengWang::getChannelInfo($chatId);
        if (!isset($chatInfo["data"]["channel_exist"])) {
            return;
        }
        if ($chatInfo["data"]["channel_exist"] == false ||
            (isset($chatInfo["data"]["users"])  && count($chatInfo["data"]["users"]) == 0)) {
            Db::startTrans();
            try {
                $chat = Db::name("chat")->where("id", $chatId)->lock(true)->find();

                if ($chat["status"] == ChatStatusEnum::WAIT_ANSWER) {
                    // 修改通话状态为发起通话失败
                    $chatUpdateData = [
                        "status" => ChatStatusEnum::NO_ANSWER,
                        "hang_up_id" => $chat["s_u_id"],
                    ];
                    Db::name("chat")->where("id", $chatId)->update($chatUpdateData);

                    // 删除临时数据：拨号状态通话，用于后期通话状态轮询
                    Db::name("tmp_wait_answer_chat")->where("chat_id", $chatId)->delete();
                }

                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }
            if ($chat["status"] == ChatStatusEnum::CALLING) {
                $service = new ChatService();
                $service->end($chat["s_u_id"], $chatId);
            }
        }
        // 声网查询在线频道信息的每个 API 调用频率上限为每秒 20 次
        usleep(50);
    }
}