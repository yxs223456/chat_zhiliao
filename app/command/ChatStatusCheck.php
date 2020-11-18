<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-04
 * Time: 11:19
 */

namespace app\command;

use app\common\enum\ChatStatusEnum;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 通话 拨号、通话 状态监测
 */
class ChatStatusCheck extends Command
{
    use CommandTrait;

    private $beginTime;

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:ChatStatusCheck');
    }

    protected function execute(Input $input, Output $output)
    {

        try {
            $this->beginTime = time();
            $redis = Redis::factory();
            while(time() - $this->beginTime <= $this->maxAllowTime) {
                $this->checkInCallChat($redis);
                $this->checkDialChat($redis);
                sleep(5);
            }
            $redis->close();
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
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

    private function checkDialChat($redis)
    {
        $chatIds = Db::name("tmp_wait_answer_chat")
            ->where("create_time", "<=", date("Y-m-d H:i:s", time()-10))
            ->column("chat_id");

        foreach ($chatIds as $chatId) {
            chatStatusCheckProduce($chatId, $redis);
        }
    }

    private function checkInCallChat($redis)
    {
        $chatIds = Db::name("tmp_calling_chat")
            ->column("chat_id");

        foreach ($chatIds as $chatId) {
            chatStatusCheckProduce($chatId, $redis);
        }
    }
}