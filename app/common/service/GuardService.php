<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


use app\common\Constant;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSexTypeEnum;
use app\common\helper\Redis;
use think\facade\Db;

class GuardService extends Base
{

    /**
     * 获取用户守护人信息
     *
     * @param $userId
     * @return array
     */
    public static function getGuard($userId)
    {
        $redis = Redis::factory();
        // 缓存有返回
        if (!empty($data = getUserGuard($userId, $redis))) {
            return $data;
        }

        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.guard_u_id = ui.u_id")
            ->leftJoin("user u","gh.guard_u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("gh.u_id", $userId)
            ->where("start_date", getLastWeekStartDate())
            ->where("end_date", getLastWeekEndDate())
            ->find();
        if (empty($data)) {
            return [];
        }
        cacheUserGuard($userId, $data, $redis);
        return $data;
    }


    /**
     * 获取用户守护人信息没有时创建
     *
     * @param $userId
     * @return array
     */
    public static function getGuard1($userId)
    {
        $redis = Redis::factory();
        // 缓存有返回
        if (!empty($data = getUserGuard($userId, $redis))) {
            return $data;
        }

        // 没有缓存查历史记录
        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.guard_u_id = ui.u_id")
            ->leftJoin("user u", "gh.guard_u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("gh.u_id", $userId)
            ->where("start_date", getLastWeekStartDate())
            ->where("end_date", getLastWeekEndDate())
            ->find();
        if (!empty($data)) {
            cacheUserGuard($userId, $data, $redis);
            return $data;
        }

        // 历史记录不存在
        // 计算用户的守护者 (女神计算男生)
        $userInfo = UserService::getUserById($userId);
        // 男生直接删除记录
        if ($userInfo['sex'] == UserSexEnum::MALE) {
            cacheUserGuard($userId, [], $redis);
            return [];
        }

        // 只查询21的数据
        $guard = Db::query("select guard_u_id,sum(amount) s from guard_charm_log where u_id = :uid and sex_type = :sex 
and create_date >= :start_date and create_date <= :end_date 
and s >= :s GROUP BY guard_u_id ORDER s desc limit 1",
            [
                'uid' => $userId,
                'sex' => UserSexTypeEnum::FEMALE_TO_MALE,
                'start_date' => getLastWeekStartDate(),
                'end_date' => getLastWeekEndDate(),
                's' => Constant::GUARD_MIN_AMOUNT
            ]);

        // 没有达到守护条件直接返回
        if (empty($guard)) {
            cacheUserGuard($userId, [], $redis);
            return [];
        }

        // 达到守护条件的添加守护历史记录,查询守护人信息
        Db::name("guard_history")->insert([
            'u_id' => $userId,
            'guard_u_id' => $guard['guard_u_id'],
            'sex_type' => UserSexTypeEnum::FEMALE_TO_MALE,
            'charm_amount' => $guard['s'],
            'start_date' => getLastWeekStartDate(),
            'end_date' => getLastWeekEndDate()
        ]);

        $data = Db::name("user_info")->alias("ui")
            ->leftJoin("user u", "ui.u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("ui.u_id", $guard['guard_u_id'])
            ->find();

        cacheUserGuard($userId, $data, $redis);
        return $data;
    }
}