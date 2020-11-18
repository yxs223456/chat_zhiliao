<?php
/**
 * Created by PhpStorm.
 * User: yanglichao
 * Date: 2020-07-01
 * Time: 16:29
 */

namespace app\command;

use app\common\Constant;
use app\common\enum\InteractSexTypeEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\WalletAddEnum;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\common\service\UserService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 守护计算任务（每周第一天凌晨会执行）
 * 1. 获取需要计算的用户
 * 2. 计算用户的守护者 (女神计算男生)
 * 3. 添加守护历史记录
 * 4. 添加守护总奖励记录
 */
class GuardCrontab extends Command
{
    use CommandTrait;

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:GuardCrontab');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->handle();
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "message" => $e->getMessage(),
            ];
            Log::write(json_encode($error), "error");
            $errorMessage = "";
            foreach ($error as $key => $value) {
                $errorMessage .= "$key: " . $value . "\n";
            }
            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yanglichao"]);
        }
    }

    /**
     * 查询一批处理
     */
    protected function handle()
    {
        $lastWeekStartEnd = getLastWeekStartDate() . "-" . getLastWeekEndDate();
        while (true) {
            $users = Db::name('guard_user_callback')->where("start_end_date", $lastWeekStartEnd)
                ->field("id,u_id")
                ->limit(0, 30)
                ->select()->toArray();
            // 没有需要处理的用户
            if (empty($users)) {
                sleep(60);
                return;
            }
            foreach ($users as $user) {
                $this->doWork($user);
            }
        }
    }

    private function doWork(array $user)
    {
        Db::startTrans();
        try {
            // 计算用户的守护者 (女神计算男生)
            $userInfo = UserService::getUserById($user['u_id']);
            if ($userInfo['sex'] == UserSexEnum::MALE) { // 男生直接删除记录
                Db::name("guard_user_callback")->where("id", $user['id'])->delete();
                Db::commit();
                return;
            }

            $redis = Redis::factory();
            // 获取上周贡献第一的人
            $firstContribute = getFemaleContributeSortSetLastWeek($user["u_id"], 0, 0, $redis);
            // 如果不存在没有守护
            if (empty($firstContribute)) {
                Db::name("guard_user_callback")->where("id", $user['id'])->delete();
                Db::commit();
                return;
            }

            $guid = key($firstContribute); // 守护的ID
            $gamount = current($firstContribute);// 守护的贡献值
            // 如果贡献值不达标不算守护
            if ($gamount < Constant::GUARD_MIN_AMOUNT) {
                Db::name("guard_user_callback")->where("id", $user['id'])->delete();
                Db::commit();
                return;
            }

            // 添加守护历史记录
            Db::name("guard_history")->insert([
                'u_id' => $user['u_id'],
                'guard_u_id' => $guid,
                'sex_type' => InteractSexTypeEnum::FEMALE_TO_MALE,
                'charm_amount' => $gamount,
                'start_date' => getLastWeekStartDate(),
                'end_date' => getLastWeekEndDate()
            ]);

            // 添加守护总奖励记录
            $exists = Db::name("guard_income")->where("u_id", $guid)->find();
            if ($exists) {
                Db::name("guard_income")->where("u_id", $guid)
                    ->inc('guard_count', 1)
                    ->inc('total_amount', $gamount)
                    ->update();
            } else {
                Db::name("guard_income")->insert([
                    'guard_count' => 1,
                    'total_amount' => $gamount,
                    'sex' => UserSexEnum::MALE,
                    'u_id' => $guid
                ]);
            }

            // 删除callback数据
            Db::name("guard_user_callback")->where("id", $user['id'])->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}