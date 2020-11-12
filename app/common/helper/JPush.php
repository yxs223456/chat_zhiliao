<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/11/11
 * Time: 下午4:58
 */

namespace app\common\helper;


use JPush\Client;
use JPush\Exceptions\JPushException;
use think\Exception;

class JPush
{
    // 推送平台标识
    const ANDROID = 1; // 安卓
    const IOS = 2; // ios
    const QUICKAPP = 3; // QuickApp
    const WINPHONE = 4; // Windows Phone

    const PLATFORM_LIST = [
        self::ANDROID => "android",
        self::IOS => "ios",
        self::QUICKAPP => "quickapp",
        self::WINPHONE => "winphone"
    ];

    protected static $key = "";
    protected static $secret = "";

    private static function getConfig()
    {
        $config = config("account.jpush");
        self::$key = $config["key"];
        self::$secret = $config["secret"];
    }

    /**
     * 给某个设备推送消息
     *
     * @param $platform
     * @param $deviceId
     * @param $message
     * @return bool
     * @throws Exception
     * @throws JPushException
     */
    public static function pushOne($platform, $deviceId, $message)
    {
        $platform = self::getPlatform($platform);
        if (empty($platform)) {
            throw new Exception("platform not empty");
        }
        if (empty($deviceId)) {
            throw new Exception("device id not empty");
        }
        if (empty($message)) {
            throw new Exception("message not empty");
        }
        self::getConfig();
        $client = new Client(self::$key, self::$secret);
        $pusher = $client->push();
        $pusher->setPlatform("all");
        $pusher->addRegistrationId($deviceId);
        $pusher->setMessage("hello push", "test push");
        try {
            $pusher->send();
        } catch (JPushException $e) {
            // try something else here
            throw $e;
        }
        return true;
    }

    /**
     * 获取单推平台
     *
     * @param $platform
     * @return mixed|string
     */
    public static function getPlatform($platform)
    {
        return isset(self::PLATFORM_LIST[$platform]) ? self::PLATFORM_LIST[$platform] : "";
    }
}