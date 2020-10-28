<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-27
 * Time: 10:21
 */

namespace app\common\helper;

class LinePay
{
    private static $channelId;
    private static $channelSecretKey;
    private static $domain;
    private static $confirmUrl;

    private static function init()
    {
        if (empty(self::$channelId) ||
            empty(self::$channelSecretKey) ||
            empty(self::$domain) ||
            empty(self::$confirmUrl)) {
            $config = config("account.line_pay");
            self::$channelId = $config["channel_id"];
            self::$channelSecretKey = $config["channel_secret_key"];
            self::$domain = $config["domain"];
            self::$confirmUrl = config("web.api_domain") . $config["confirm_url"];
        }
    }

    private static function getPostHeader($uri, $requestBody)
    {
        $nonce = getRandomString();
        $signData = self::$channelSecretKey . $uri . $requestBody . $nonce;
        $signature = base64_encode(hash_hmac("sha256", $signData, self::$channelSecretKey));
        return [
            "Content-Type: application/json",
            "X-LINE-ChannelId: " . self::$channelId,
            "X-LINE-Authorization-Nonce: " . $nonce,
            "X-LINE-Authorization: " . $signature,
        ];
    }

    /**
     * 发起支付
     * @param $amount number 支付金额
     * @param $orderId string 100 商户支付订单号
     * @param $shopName string 100 商品名称
     * @param $cancelUrl string 500 取消支付后跳转地址
     * @param string $currency string 3 支付货币：USD、JPY、TWD、THB
     * @return mixed
     * @throws \Exception
     */
    public static function requestApi($amount, $orderId, $shopName, $cancelUrl, $currency = "TWD")
    {
        self::init();
        $params = [
            "amount" => $amount,
            "currency" => $currency,
            "orderId" => $orderId,
            "packages" => [
                [
                    "id" => getRandomString(),
                    "amount" => $amount,
                    "name" => $shopName,
                    "products" => [
                        "name" => $shopName,
                        "quantity" => 1,
                        "price" => $amount,
                    ],
                ]
            ],
            "redirectUrls" => [
                "confirmUrl" => self::$confirmUrl,
                "confirmUrlType" => "SERVER",
                "cancelUrl" => $cancelUrl,
            ],
        ];
        $requestBody = json_encode($params, JSON_UNESCAPED_UNICODE);
        $uri = "/v3/payments/request";
        $headers = self::getPostHeader($uri, $requestBody);
        $url = self::$domain . $uri;
        $response = curl($url, "post", $requestBody, false, true, $headers);

        if (!isset($response["returnCode"])) {
            throw new \Exception("line pay error");
        } elseif ($response["returnCode"] !== "0000") {
            throw new \Exception($response["returnMessage"]);
        } else {
            return $response;
        }
    }

    public static function confirmApi($transactionId, $amount, $currency)
    {
        self::init();
        $params = [
            "amount" => $amount,
            "currency" => $currency,
        ];
        $requestBody = json_encode($params, JSON_UNESCAPED_UNICODE);
        $uri = "/v3/payments/$transactionId/confirm";
        $headers = self::getPostHeader($uri, $requestBody);
        $url = self::$domain . $uri;
        $response = curl($url, "post", $requestBody, false, true, $headers);

        if (!isset($response["returnCode"])) {
            throw new \Exception("line pay confirm error");
        } else {
            return $response;
        }
    }
}