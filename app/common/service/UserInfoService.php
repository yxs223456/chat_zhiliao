<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:49
 */

namespace app\common\service;

use app\common\helper\Redis;
use app\common\model\UserInfoModel;

class UserInfoService extends Base
{
    public static function getUserInfoById($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUserInfo = getUserInfoDataByUId($userId, $redis);
        if (empty($cacheUserInfo['u_id'])) {
            $model = new UserInfoModel();
            $userInfo = $model->findByUId($userId);
            if ($userInfo) {
                $cacheUserInfo = $userInfo->toArray();
                cacheUserInfoDataByUId($cacheUserInfo, $redis);
            }
        }
        return $cacheUserInfo;
    }
}