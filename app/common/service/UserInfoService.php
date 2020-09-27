<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:49
 */

namespace app\common\service;

use app\common\helper\Redis;
use think\facade\Db;

class UserInfoService extends Base
{
    public static function getUserInfoById($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $userInfo = getUserInfoDataByUId($userId, $redis);
        if (empty($userInfo['u_id'])) {
            $userInfo = Db::name("user_info")->alias("ui")
                ->leftJoin("user u","ui.u_id = u.id")
                ->field("ui.*,u.sex,u.user_number")
                ->where("ui.u_id",$userId)
                ->find();
            if ($userInfo) {
                cacheUserInfoDataByUId($userInfo, $redis);
            }
        }
        return $userInfo;
    }
}