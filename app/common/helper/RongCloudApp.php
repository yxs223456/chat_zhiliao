<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-19
 * Time: 14:53
 */

use RongCloud\RongCloud;

/**
 * 融云
 * Class RongCloud
 */
class RongCloudApp
{
    private static $appKey = null;
    private static $appSecret = null;

    private static function init()
    {
        if (self::$appKey == null || self::$appSecret == null) {
            $config = config("account.rong_cloud");
            self::$appKey = $config["app_key"];
            self::$appSecret = $config["app_secret"];
        }
    }

    /**
     * 注册用户
     * @param $rcUserId
     * @param $userName
     * @param $headImageUrl
     * @return array
     */
    public static function register($rcUserId, $userName, $headImageUrl) {
        self::init();
        $RongSDK = new RongCloud(self::$appKey, self::$appSecret);
        $user = [
            'id' => $rcUserId, //用户id
            'name' => $userName,//用户名称
            'portrait' => $headImageUrl //用户头像
        ];
        $register = $RongSDK->getUser()->register($user);
        return $register;
    }

    /**
     * 修改用户信息
     * @param $rcUserId
     * @param $userName
     * @param $headImageUrl
     */
    public static function updateUserInfo($rcUserId, $userName, $headImageUrl)
    {
        self::init();
        $RongSDK = new RongCloud(self::$appKey, self::$appSecret);
        $user = [
            'id' => $rcUserId,//用户id
            'name' => $userName,//用户名称
            'portrait' => $headImageUrl //用户头像
        ];
        $RongSDK->getUser()->update($user);
    }
}