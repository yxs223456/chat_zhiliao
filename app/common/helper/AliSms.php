<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/4/26
 * Time: 下午5:15
 */

namespace app\common\helper;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use think\facade\Log;

/**
 * Class SendSms
 * @package app\common\helper
 * 短信发送类
 */
class AliSms
{
    // 用户注册模版
    const CODE_TEMPLATE = "SMS_197870801";
    // 用户注册国际模版
    const CODE_INTERNATIONAL_TEMPLATE = "SMS_197870779";
    // 用户注册签名
    const CODE_SIGN = "一克拉商城";

    // 国内
    const TYPE_CHINA = 1;
    // 国际
    const TYPE_INTERNATIONAL = 2;

    // 模版数组
    private static $TYPE_LIST_TO_TEMPLATE = [
        self::TYPE_CHINA => self::CODE_TEMPLATE,
        self::TYPE_INTERNATIONAL => self::CODE_INTERNATIONAL_TEMPLATE
    ];

    /**
     * 获取AcsClient
     */
    public static function getAcsClient()
    {
        // 暂时不支持多Region
        $region = "cn-hangzhou";
        $config = config('account.sms');
        $accessKeyId = $config['accessKeyId']; // AccessKeyId
        $accessKeySecret = $config['accessSecret']; // AccessKeySecret

        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($region)
            ->asDefaultClient();
    }

    public static function sendSms($mobile, $param = array(), &$response = array(), $type = self::TYPE_CHINA)
    {
        try {
            self::getAcsClient();

            $query = [
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'PhoneNumbers' => $mobile,
                    'SignName' => self::CODE_SIGN,
                    'TemplateCode' => self::getTemplate($type),
                ]
            ];
            if (!empty($param)) {
                $query['query']['TemplateParam'] = json_encode($param);
            }

            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                // ->scheme('https') // https | http
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')
                ->options($query)
                ->request();
            $res = $result->toArray();
            $response['response'] = $res;
            return $res;
        } catch (ClientException $e) {
            Log::error("[短信发送失败] " . $e->getErrorMessage() . ' ' . json_encode([$mobile, $param]));
            $response['error'] = $e->getErrorMessage();
            return [];
        } catch (ServerException $e) {
            Log::error("[短信发送失败] " . $e->getErrorMessage() . ' ' . json_encode([$mobile, $param]));
            $response['error'] = $e->getErrorMessage();
            return [];
        }
    }

    /**
     * 获取模版类型，默认国内
     *
     * @param $type
     * @return mixed|string
     */
    private static function getTemplate($type)
    {
        return isset(self::$TYPE_LIST_TO_TEMPLATE[$type]) ? self::$TYPE_LIST_TO_TEMPLATE[$type] : self::CODE_TEMPLATE;
    }
}