<?php

namespace app\common\helper;

use GuzzleHttp\Client;
use think\facade\Log;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/4/27
 * Time: 下午6:12
 */
class WechatLogin
{
    private static $object = null;
    private static $driver = '';
    private $config = null;
    private $user = null;

    protected $baseUrl = 'https://api.weixin.qq.com/sns';

    protected $scopes = ['snsapi_login'];

    protected static $guzzleOptions = ['http_errors' => false];

    /**
     * ThirdLogin constructor.
     * @param $driver
     */
    private function __construct($driver)
    {
        $this->config = config("account.wechat." . $driver);
        self::$driver = $driver;
    }

    /**
     * 创建实例
     *
     * @param string $driver (wxapp|wxh5)
     * @return WechatLogin|null
     */
    public static function getObject($driver = "wxapp")
    {
        if (!self::$object instanceof self || self::$driver != $driver) {
            self::$object = new self($driver);
        }
        return self::$object;
    }

    /**
     * 获取用户数据
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function getUser($code = "")
    {
        $this->user = $this->getUserByToken($this->getAccessToken($code));
        return $this->user;
    }

    public function getNickname()
    {
        return isset($this->user["nickname"]) ? $this->user['nickname'] : "";
    }

    public function getHeadimgurl()
    {
        return isset($this->user['headimgurl']) ? $this->user['headimgurl'] : "";
    }

    public function getOpenid()
    {
        return isset($this->user["openid"]) ? $this->user['openid'] : "";
    }

    public function getUnionid()
    {
        return isset($this->user["unionid"]) ? $this->user["unionid"] : "";
    }

    public function getSex()
    {
        return isset($this->user["sex"]) ? $this->user["sex"] : "";
    }

    /**
     * 获取用户数据
     *
     * @param array $token
     * @return mixed
     * @throws \Exception
     */
    private function getUserByToken(array $token)
    {
        if (empty($token['openid'])) {
            Log::error("[ThirdLogin error] : openid of AccessToken is required. ");
            throw new \Exception('openid of AccessToken is required.');
        }

        $response = $this->getHttpClient()->get($this->baseUrl . '/userinfo', [
            'query' => array_filter([
                'access_token' => $token['access_token'],
                'openid' => $token['openid'],
                'lang' => 'zh_CN',
            ]),
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * 通过code获取access_token数据
     *
     * @param $code
     * @return mixed
     */
    private function getAccessToken($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * {@inheritdoc}.
     */
    private function getTokenUrl()
    {
        return $this->baseUrl . '/oauth2/access_token';
    }

    /**
     * {@inheritdoc}.
     */
    private function getTokenFields($code)
    {
        return array_filter([
            'appid' => $this->getConfig("app_id"),
            'secret' => $this->getConfig("app_secret"),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * 获取http client
     *
     * @return \GuzzleHttp\Client
     */
    private function getHttpClient()
    {
        return new Client(self::$guzzleOptions);
    }

    /**
     * 获取access_token
     *
     * @param $body
     * @return mixed
     * @throws \Exception
     */
    private function parseAccessToken($body)
    {
        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body['access_token'])) {
            Log::error("[ThirdLogin error] : " . json_encode($body));
            throw new \Exception('Authorize Failed: ' . json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        return $body;
    }

    /**
     * 获取配置文件
     *
     * @param $key
     * @return string
     */
    private function getConfig($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : "";
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
}