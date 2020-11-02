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
    private static $clientId;
    private static $clientSecret;

    private static function init()
    {
        if (empty(self::$appId) ||
            empty(self::$appCertificate) ||
            empty(self::$clientId) ||
            empty(self::$clientSecret)) {
            $config = config("account.sheng_wang");
            self::$appId = $config["app_id"];
            self::$appCertificate = $config["app_certificate"];
            self::$clientId = $config["client_id"];
            self::$clientSecret = $config["client_secret"];
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
        $userId = (string) $userId;

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

    /**
     * 发送消息
     * @param $userId
     * @param $sendUserId
     * @param $message
     * @return mixed
     * @throws \Exception
     */
    public static function sendMessage($userId, $sendUserId, $message)
    {
        self::init();
        $headers = [
            "Content-type: application/json;charset=utf-8",
            "x-agora-token: " . self::getRtmToken($userId),
            "x-agora-uid: " . $userId,
            "Authorization: " . self::getApiAuthorization(),
        ];

        $url = "https://api.agora.io/dev/v2/project/".self::$appId."/rtm/users/".$userId."/peer_messages?wait_for_ack=false";
        $params = [
            "destination" => (string) $sendUserId,
            "enable_offline_messaging" => true,
            "enable_historical_messaging" => false,
            "payload" => $message,
        ];

        $response = curl($url, 'post', $params, true, true, $headers);

        return $response;
    }

    private static function getApiAuthorization()
    {
        self::init();
        return base64_encode(self::$clientId . ":" . self::$clientSecret);
    }
}