<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\helper\JPush;
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
class TestJPush extends Command
{
    use CommandTrait;


    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:TestPush');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $ext = [
                "nickname" => "隔壁老王",
                "avatar" => "这是头像",
                "u_id" => "这是老王的ID"
            ];
           $result = JPush::pushOne("33","视频接听","隔壁老王的视频来电",$ext);
            print_r($result);

        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "message" => $e->getMessage(),
            ];
            Log::write(json_encode($error), "error");
//            $errorMessage = "";
//            foreach ($error as $key=>$value) {
//                $errorMessage .= "$key: " . $value . "\n";
//            }
//            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yanglichao"]);
        }
    }

}