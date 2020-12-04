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
     * @param $tag string 设备用户绑定标签
     * @param $content string 内容
     * @param $title string 标题
     * @param $extra array 业务数据
     * @return array|bool
     * @throws Exception
     * @throws JPushException
     */
    public static function pushOne($tag, $content, $title, $extra = [])
    {
        if (empty($tag)) {
            throw new Exception("tag  not empty"); 
        }
        if (empty($content) || empty($title)) {
            throw new Exception("content and title not empty");
        }
        self::getConfig();
        $client = new Client(self::$key, self::$secret);

        $iosN = ['title' => $title, 'subtitle' => $content];
        try {
            $pusher = $client->push();
            return $pusher->setPlatform("all")
                ->addAlias((string)$tag)
                ->setNotificationAlert($title)
                ->iosNotification($iosN, ['content-available' => true, 'mutable-content' => true, "extras" => $extra])
                ->androidNotification($title, ["title" => $content, 'extras' => $extra])
                ->send();
        } catch (JPushException $e) {
            // try something else here
            throw $e;
        }
        return true;
    }

}