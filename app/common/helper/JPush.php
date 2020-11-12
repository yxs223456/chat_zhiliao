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
     * @param $deviceId
     * @param $message
     * @return bool
     * @throws Exception
     * @throws JPushException
     */
    public static function pushOne($deviceId, $message)
    {
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

}