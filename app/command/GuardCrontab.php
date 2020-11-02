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
        $lastWeekStartEnd = getLastWeekEndDate() . "-" . getLastWeekEndDate();
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

            // 只查询收入类型为3，4，5，6，7的数据
            $addType = implode(",",
                [WalletAddEnum::GIFT,
                    WalletAddEnum::VIDEO_CHAT,
                    WalletAddEnum::VOICE_CHAT,
                    WalletAddEnum::RED_PACKAGE,
                    WalletAddEnum::DIRECT_MESSAGE]);
            $guard = Db::query("select add_u_id,sum(amount) as s from user_wallet_flow as uwf left join user as u on u.id = uwf.add_u_id where uwf.u_id = :uid and uwf.add_type in($addType) 
and u.sex = :sex and uwf.create_date >= :start_date and uwf.create_date <= :end_date 
GROUP BY uwf.add_u_id having s >= :s ORDER by s desc limit 1",
                [
                    'uid' => $user["u_id"],
                    'sex' => UserSexEnum::MALE,
                    'start_date' => getLastWeekStartDate(),
                    'end_date' => getLastWeekEndDate(),
                    's' => Constant::GUARD_MIN_AMOUNT
                ]);

            if (empty($guard)) { // 没有达到守护条件直接删除
                Db::name("guard_user_callback")->where("id", $user['id'])->delete();
                Db::commit();
                return;
            }

            // 添加守护历史记录
            Db::name("guard_history")->insert([
                'u_id' => $user['u_id'],
                'guard_u_id' => $guard['add_u_id'],
                'sex_type' => InteractSexTypeEnum::FEMALE_TO_MALE,
                'charm_amount' => $guard['s'],
                'start_date' => getLastWeekStartDate(),
                'end_date' => getLastWeekEndDate()
            ]);

            // 添加守护总奖励记录
            $exists = Db::name("guard_income")->where("u_id", $guard['add_u_id'])->find();
            if ($exists) {
                Db::name("guard_income")->where("u_id", $guard['add_u_id'])
                    ->inc('guard_count', 1)
                    ->inc('total_amount', $guard['s'])
                    ->update();
            } else {
                Db::name("guard_income")->insert([
                    'guard_count' => 1,
                    'total_amount' => $guard['s'],
                    'sex' => UserSexEnum::MALE,
                    'u_id' => $guard['add_u_id']
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