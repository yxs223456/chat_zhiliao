<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/21
 * Time: 下午2:13
 */

namespace app\common\service;

use app\common\helper\Redis;

class VisitorService extends Base
{

    /**
     * 添加访问记录
     *
     * @param $userId int 被访问用户ID
     * @param $visitorId int 访问用户ID
     * @param null $redis
     * @return array|mixed
     */
    public static function addVisitorLog($userId, $visitorId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        // 放入操作队列
        cacheUserVisitorIdDataQueue($userId, $visitorId, $redis);
    }
}