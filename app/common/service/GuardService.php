<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


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


}