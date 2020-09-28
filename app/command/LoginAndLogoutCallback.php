<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 15:14
 */

namespace app\command;

use app\common\enum\UserSexEnum;
use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\common\service\UserService;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 用户登录、退出后回调
 */
class LoginAndLogoutCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $userId = "";
    private $do = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:LoginAndLogoutCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
            loginAndLogoutCallbackConsumer([$this, 'receive']);
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "u_id" => $this->userId,
                "do" => $this->do,
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
        if (!is_array($msgArray) ||
            empty($msgArray["u_id"]) ||
            empty($msgArray["do"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->userId = $msgArray["u_id"];
        $this->do = $msgArray["do"];

        $this->doWork($msgArray, $message);
    }

    private function doWork(array $msgArray, AMQPMessage $message)
    {
        try {
            $userId = $msgArray["u_id"];
            $do = strtolower($msgArray["do"]);

            switch ($do) {
                case "login":
                    $this->loginCallback($userId);
                    break;
                case "logout":
                    $this->logoutCallback($userId);
                    break;
            }

        } catch (\Throwable $e) {
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }

    private function loginCallback($userId)
    {
        $redis = Redis::factory();

        // 如果用户是女生并且设置了相册，把用户放入首页推荐列表
        $user = UserService::getUserById($userId, $redis);
        $userInfo = Db::name("user_info")->where("u_id", $userId)->find();
        $photos = json_decode($userInfo["photos"], true);
        if ($user["sex"] == UserSexEnum::FEMALE && count($photos) > 0) {
            $userWallet = Db::name("user_wallet")->where("u_id", $userId)->find();
            addUserToHomeRecommendList($userId, $userWallet["income_total_amount"], $redis);

            // 如果是一周内新注册女生，把用户放入首页新人列表
            if (time() - strtotime($user["create_time"]) <= 7 * 86400) {
                addUserToHomeNewUserList($userId, $userWallet["income_total_amount"], $redis);
            }
        }

        // 修改用户最后登录时间、重新生成用户缓存
        Db::name("user_info")->where("id", $userInfo["id"])->update([
            "last_login_time" => time(),
        ]);
        $userInfo["last_login_time"] = time();
        cacheUserInfoDataByUId($userInfo, $redis);

        $redis->close();
    }

    private function logoutCallback($userId)
    {
        $redis = Redis::factory();

        // 把用户移出首页推荐集合（不验证用户是否在集合内）
        deleteUserFromHomeRecommendList($userId, $redis);

        // 把用户移出首页新人集合（不验证用户是否在集合内）
        deleteUserFromHomeNewUserList($userId, $redis);

        $redis->close();
    }
}