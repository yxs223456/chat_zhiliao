<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-26
 * Time: 11:13
 */

namespace app\common\helper;

use think\facade\App;

include_once App::getRootPath() . "/extend/shengwang/RtmTokenBuilder.php";
include_once App::getRootPath() . "/extend/shengwang/RtcTokenBuilder.php";

class ShengWang
{
    private static $appId;
    private static $appCertificate;

    private static function init()
    {
        if (empty(self::$appId) || empty(self::$appCertificate)) {
            $config = config("account.sheng_wang");
            self::$appId = $config["app_id"];
            self::$appCertificate = $config["app_certificate"];
        }
    }

    /**
     * 生成RTM token
     * @param $userId
     * @param int $expire
     * @return string
     * @throws \Exception
     */
    public static function getRtmToken($userId, $expire = 86400)
    {
        self::init();
        $appID = self::$appId;
        $appCertificate = self::$appCertificate;

        $role = \RtmTokenBuilder::RoleRtmUser;
        $expireTimeInSeconds = $expire;
        $currentTimestamp = (new \DateTime("now", new \DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $token = \RtmTokenBuilder::buildToken($appID, $appCertificate, $userId, $role, $privilegeExpiredTs);
        return $token;
    }

    /**
     * 生成RTC token
     * @param $userId
     * @param $channelName
     * @param int $expire
     * @return string
     * @throws \Exception
     */
    public static function getRtcToken($userId, $channelName, $expire = 86400)
    {
        self::init();
        $channelName = (string) $channelName;

        $appID = self::$appId;
        $appCertificate = self::$appCertificate;

        $role = \RtcTokenBuilder::RoleAttendee;
        $currentTimestamp = (new \DateTime("now", new \DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expire;

        $token = \RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $userId, $role, $privilegeExpiredTs);
        return $token;
    }
}