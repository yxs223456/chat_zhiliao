<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-20
 * Time: 17:05
 */

namespace app\common\helper;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class AliMobilePhoneCertificate
{
    private static $access_key_id = null;
    private static $access_secret = null;
    private static $region_id = "cn-hangzhou";

    private static function setAccessKeyClient()
    {
        if (self::$access_key_id == null) {
            $config = config("account.ali_mobile_phone_certificate");
            self::$access_key_id = $config["accessKeyId"];
            self::$access_secret = $config["accessSecret"];
        }

        AlibabaCloud::accessKeyClient(self::$access_key_id, self::$access_secret)
            ->regionId(self::$region_id)
            ->asDefaultClient();
    }

    /**
     * 一键登录获取手机号
     * @param $tokenForGetMobile string app端SDK获取的登录token
     * @return mixed
     * @throws ClientException
     * @throws ServerException
     */
    public static function getMobile($tokenForGetMobile)
    {
        self::setAccessKeyClient();

        $result = AlibabaCloud::rpc()
            ->product('Dypnsapi')
            ->version('2017-05-25')
            ->action('GetMobile')
            ->method('POST')
            ->host('dypnsapi.aliyuncs.com')
            ->options([
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'AccessToken' => $tokenForGetMobile,
                ],
            ])
            ->request();

        $response = $result->toArray();
        if (isset($response["GetMobileResultDTO"]["Mobile"])) {
            return $response["GetMobileResultDTO"]["Mobile"];
        }
        if (isset($response["Message"])) {
            throw new \Exception("手机号获取失败：" . $response["Message"]);
        } else {
            throw new \Exception("手机号获取失败：未知原因");
        }
    }
}