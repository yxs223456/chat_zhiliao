<?php
/**
 * Created by PhpStorm.
 * User: yanglichao
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\Constant;
use app\common\enum\FlowTypeEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\WalletAddEnum;
use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\common\service\GuardService;
use app\common\service\UserService;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 聊天，送礼等交易发生后记录魅力值和守护分润
 * 1. 添加魅力值贡献记录
 * 2. 添加守护分润流水
 * 3. 更新守护钱包金额
 * 4. 更新守护奖励总记录表
 */
class GuardCallback extends Command
{
    use CommandTrait;

    private $beginTime;
    private $incomeUserId = ""; // 赚钱人ID
    private $spendUserId = ""; // 花钱人ID
    private $coin = 0; // 实际赚聊币数量

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:GuardCallback');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
            userGuardCallBackConsumer([$this, 'receive']);
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "income_user_id" => $this->incomeUserId,
                "spend_user_id" => $this->spendUserId,
                "coin" => $this->coin,
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
        if (!is_array($msgArray) || empty($msgArray["incomeUserId"]) || empty($msgArray["spendUserId"]) || empty($msgArray["coin"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->incomeUserId = $msgArray["incomeUserId"];
        $this->spendUserId = $msgArray["spendUserId"];
        $this->coin = $msgArray["coin"];

        $this->doWork($message);
    }

    private function doWork(AMQPMessage $message)
    {
        // 男神女神信息
        $prettyUser = UserService::getUserById($this->incomeUserId);
        // 贡献人信息
        $spendUser = UserService::getUserById($this->spendUserId);
        // 上周守护人
        $guardUser = GuardService::getGuard($this->incomeUserId);

        Db::startTrans();
        try {
            // 添加魅力值贡献记录
            Db::name("guard_charm_log")
                ->insert([
                    'u_id' => $this->incomeUserId,
                    'guard_u_id' => $this->spendUserId,
                    'sex_type' => $prettyUser["sex"] . $spendUser['sex'],
                    'amount' => $this->coin,
                    'create_date' => date("Y-m-d"),
                ]);

            // 添加需要统计守护的女神ID
            $startEndDate = implode("-",getWeekStartAndEnd());
            $exists = Db::name("guard_user_callback")->where("u_id", $this->incomeUserId)
                ->where("start_end_date", $startEndDate)->find();
            if (!$exists) {
                Db::name("guard_user_callback")->insert([
                    'u_id' => $this->incomeUserId,
                    'start_end_date' => $startEndDate
                ]);
            }

            // 如果守护存在计算守护
            if ($guardUser) {
                // 更新守护钱包金额
                $addCoin = round($this->coin * Constant::GUARD_SHARE_RATE);
                if ($addCoin < Constant::GUARD_SHARE_MIN_COIN) {
                    $addCoin = Constant::GUARD_SHARE_MIN_COIN;
                }
                Db::name("user_wallet")->where("u_id", $guardUser["u_id"])
                    ->inc('income_total_amount', $addCoin)
                    ->inc('balance_amount', $addCoin)
                    ->inc('total_balance', $addCoin)
                    ->update();
                // 添加守护分润流水
                $wallet = Db::name('user_wallet')->where("u_id", $guardUser['u_id'])->lock(true)->find();
                Db::name("user_wallet_flow")->insert([
                    'u_id' => $guardUser["u_id"],
                    'flow_type' => FlowTypeEnum::ADD,
                    'amount' => $addCoin,
                    'add_type' => WalletAddEnum::ANGEL,
                    'object_source_id' => $this->incomeUserId,// 造成分润的女神ID
                    'before_balance' => $wallet["total_balance"] ?? 0,
                    'after_balance' => empty($wallet['total_balance']) ? $addCoin : $wallet['total_balance'] + $addCoin,
                    'create_date' => date("Y-m-d")
                ]);

                // 更新守护奖励总记录表
                Db::name("guard_income")->where("u_id", $guardUser['u_id'])
                    ->inc('total_amount', $addCoin)
                    ->update();
            }

            Db::commit();

            // 当前业务规则逻辑(只有男生送女神才互相计算魅力值，参加魅力排行，符合条件删除女神本周的排行缓存)
            if ($prettyUser['sex'] == UserSexEnum::FEMALE && $spendUser['sex'] == UserSexEnum::MALE) {
                list($startDate, $endDate) = getWeekStartAndEnd();
                deletePrettyWeekContributionList($this->incomeUserId, $startDate, $endDate, Redis::factory());
            }

        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }
}