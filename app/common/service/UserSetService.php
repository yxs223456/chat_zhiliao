<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:49
 */

namespace app\common\service;

use app\common\helper\Redis;
use app\common\model\UserSetModel;

class UserSetService extends Base
{
    public static function getUserSetByUId($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUserSet = getUserSetByUId($userId, $redis);
        if (empty($cacheUserSet['id'])) {
            $model = new UserSetModel();
            $userSet = $model->findByUId($userId);
            if ($userSet) {
                $cacheUserSet = $userSet->toArray();
                cacheUserSetByUId($cacheUserSet, $redis);
            }
        }
        return $cacheUserSet;
    }
}