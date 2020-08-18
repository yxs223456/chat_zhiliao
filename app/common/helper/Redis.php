<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/11/13
 * Time: 14:04
 */
namespace app\common\helper;

class Redis
{
    public static function factory()
    {
        $redisConfig = config('account.redis');
        $redis = new \Redis();
        $redis->connect($redisConfig["host"], $redisConfig["port"], $redisConfig["timeout"]);

        if ($redisConfig["password"] != "") {
            $redis->auth($redisConfig["password"]);
        }
        return $redis;
    }
}