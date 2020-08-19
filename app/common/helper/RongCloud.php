<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-19
 * Time: 14:53
 */

/**
 * 融云
 * Class RongCloud
 */
class RongCloud
{
    private static $appKey = null;
    private static $appSecret = null;

    private static function initConfig()
    {
        if (self::$appKey == null || self::$appSecret == null) {
            $config = config("account.rong_cloud");
            self::$appKey = $config["app_key"];
            self::$appSecret = $config["app_secret"];
        }
    }

}