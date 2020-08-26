<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-20
 * Time: 15:12
 */

namespace app\common\helper;

class WeChatWork
{
    public static $user = [
        "yangxiushan" => "YangXiuShan",
        "yanglichao" => "d41d8cd98f00b204e9800998ecf8427e",
    ];

    private static $corp_id = null;
    private static $secret = null;
    private static $agent_id = null;

    private static function init()
    {
        if (self::$corp_id == null) {
            $config = config("account.we_chat_work");
            self::$corp_id = $config["corp_id"];
            self::$secret = $config["secret"];
            self::$agent_id = $config["agent_id"];
        }
    }

    private static function getAccessToken()
    {
        self::init();
        $redis = Redis::factory();
        $accessToken = getWeChatWorkAccessToken($redis);
        if (!$accessToken) {
            $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".self::$corp_id."&corpsecret=".self::$secret;
            $response = curl($url, 'get', '', '', true);
            if (isset($response["access_token"])) {
                $accessToken = $response["access_token"];
                setWeChatWorkAccessToken($accessToken, $response["expires_in"] - 200, $redis);
            } else {
                throw new \Exception("when get we_chat_worker access_token error");
            }
        }
        return $accessToken;
    }

    public static function sendMessageToUser($message, $user = null)
    {
        $accessToken = self::getAccessToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=" . $accessToken;
        if (is_array($user)) {
            if (count($user) == 1) {
                $user = $user[0];
            } else {
                $user = implode("|", $user);
            }
        }
        $requestData = [
            "touser" => $user ? $user : implode("|", self::$user),
            "msgtype" => "text",
            "agentid" => self::$agent_id,
            "text" => [
                "content" => $message,
            ],
        ];
        curl($url, "post", $requestData, true);
    }
}