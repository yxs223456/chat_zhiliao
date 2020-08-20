<?php

namespace app\common\helper;

use think\Exception;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/5/6
 * Time: 下午6:10
 */
class WechatPay
{

    private static $config = null;

    /**
     * 获取配置参数
     *
     * @param $key
     * @return string
     */
    protected static function getConfig($key)
    {
        if (empty(self::$config)) {
            self::$config = config('account.wechat');
        }

        if (strpos($key, ".") === false) {
            return isset(self::$config[$key]) ? self::$config[$key] : "";
        }

        $keyArr = explode(".", $key);
        return isset(self::$config[$keyArr[0]][$keyArr[1]]) ? self::$config[$keyArr[0]][$keyArr[1]] : "";
    }

    /**
     * 微信JSAPI支付
     *
     * @param $body string 商品描述
     * @param $outTradeNo string 商户订单号
     * @param $totalFee int 商品价格 (分)
     * @return array
     */
    public static function jsapi($body, $outTradeNo, $totalFee)
    {
        $data = [
            'body' => $body,
            'out_trade_no' => $outTradeNo,
            'total_fee' => $totalFee,
            'notify_url' => self::getConfig("notify_url"),
            'trade_type' => 'JSAPI'
        ];

        $unifiedOrder = self::unifiedOrder($data);
        $prepayId = isset($unifiedOrder['prepay_id']) ? $unifiedOrder["prepay_id"] : "";
        $payRequest = [
            'appId' => self::getConfig('wxh5.app_id'),
            'timeStamp' => strval(time()),
            'nonceStr' => self::getNonceStr(),
            'package' => 'prepay_id=' . $prepayId,
            'signType' => self::getConfig('sign_type'),
        ];
        $payRequest['paySign'] = self::getSign($payRequest);

        return $payRequest;
    }

    /**
     * 微信h5支付
     *
     * @param $body string 商品描述
     * @param $outTradeNo string 商户订单号
     * @param $totalFee int 商品价格 (分)
     * @param $sceneInfo array 场景信息
     * @return string
     */
    public static function h5($body, $outTradeNo, $totalFee, array $sceneInfo)
    {
        $data = [
            'body' => $body,
            'out_trade_no' => $outTradeNo,
            'total_fee' => $totalFee,
            'scene_info' => $sceneInfo,
            'trade_type' => 'MWEB'
        ];

        $unifiedOrder = self::unifiedOrder($data);

        return empty($unifiedOrder["mweb_url"]) ? "" : $unifiedOrder["mweb_url"];
    }

    /**
     * 微信app支付
     *
     * @param $body string 商品描述
     * @param $outTradeNo string 商户订单号
     * @param $totalFee int 商品价格 (分)
     * @return array
     */
    public static function app($body, $outTradeNo, $totalFee)
    {
        $data = [
            'body' => $body,
            'out_trade_no' => $outTradeNo,
            'total_fee' => $totalFee,
            "trade_type" => "APP"
        ];

        $unifiedOrder = self::unifiedOrder($data, "app");
        $prepayId = isset($unifiedOrder['prepay_id']) ? $unifiedOrder["prepay_id"] : "";
        $payRequest = [
            'appid' => self::getConfig("app_id"),
            'partnerid' => self::getConfig('mch_id'),
            'prepayid' => $prepayId,
            'timestamp' => strval(time()),
            'noncestr' => self::getNonceStr(),
            'package' => 'Sign=WXPay',
        ];
        $payRequest['sign'] = self::getSign($payRequest);

        return $payRequest;
    }

    /**
     * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
     *
     * @param $data
     * @param $type
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function orderQuery($data, $type = "", $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        //检测必填参数
        if (empty($data["out_trade_no"]) && empty($data["transaction_id"])) {
            throw new \Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }
        if ($type == 'app') {
            $data["appid"] = self::getConfig("wxapp.app_id"); //APP应用ID
        } else {
            $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        }
        $data["mch_id"] = self::getConfig("mch_id");// 商户号
        $data["nonce_str"] = self::getNonceStr(); //随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);
    }

    /**
     * 关闭订单，$data中out_trade_no必填
     *
     * @param array $data
     * @param string $type
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function closeOrder(array $data, $type = '', $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/closeorder";
        //检测必填参数
        if (empty($data["out_trade_no"])) {
            throw new \Exception("订单查询接口中，out_trade_no必填！");
        }

        if ($type == 'app') {
            $data["appid"] = self::getConfig("wxapp.app_id"); //APP应用ID
        } else {
            $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        }

        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);
    }

    /**
     * 申请退款，$data中out_trade_no、transaction_id至少填一个且
     * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param string $type
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function refund(array $data, $type = "", $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        //检测必填参数
        if (empty($data["out_trade_no"]) && empty($data["transaction_id"])) {
            throw new \Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！");
        }
        if (empty($data["out_refund_no"])) {
            throw new \Exception("退款申请接口中，缺少必填参数out_refund_no！");
        }
        if (empty($data["total_fee"])) {
            throw new \Exception("退款申请接口中，缺少必填参数total_fee！");
        }
        if (empty($data["refund_fee"])) {
            throw new \Exception("退款申请接口中，缺少必填参数refund_fee！");
        }
        if (empty($data["op_user_id"])) {
            throw new \Exception("退款申请接口中，缺少必填参数op_user_id！");
        }

        if ($type == 'app') {
            $data["appid"] = self::getConfig("wxapp.app_id"); //APP应用ID
        } else {
            $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        }

        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, true, $timeOut);
        return self::response($response);
    }

    /**
     * 查询退款
     * 提交退款申请后，通过调用该接口查询退款状态。退款有一定延时，
     * 用零钱支付的退款20分钟内到账，银行卡支付的退款3个工作日后重新查询退款状态。
     * $data、out_trade_no、transaction_id、refund_id四个参数必填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param string $type
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function refundQuery(array $data, $type = "", $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/refundquery";
        //检测必填参数
        if (empty($data["out_refund_no"]) &&
            empty($data["out_trade_no"]) &&
            empty($data["transaction_id"]) &&
            empty($data["refund_id"])
        ) {
            throw new \Exception("退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个！");
        }

        if ($type == 'app') {
            $data["appid"] = self::getConfig("wxapp.app_id"); //APP应用ID
        } else {
            $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        }

        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);
    }

    /**
     * 支付结果通用通知
     * 直接回调函数使用方法: notify(you_function);
     * 回调类成员函数方法:notify(array($this, you_function));
     * $callback  原型为：function function_name($data){}
     *
     * @param $callback
     * @param $msg
     * @return bool|mixed
     */
    public static function notify($callback, &$msg)
    {
        //获取通知的数据
        $xml = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
        if (empty($xml)) {
            # 如果没有数据，直接返回失败
            return false;
        }

        //如果返回成功则验证签名
        try {
            $result = self::notifyResponse($xml);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            return false;
        }

        return call_user_func($callback, $result);
    }

    /***********************************************wechat.Api***************************************************************/

    /**
     * SDK版本号
     * @var string
     */
    public static $VERSION = "3.0.10";


    /**
     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
     *
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param array $data
     * @param string $type 类型jsapi,app,h5不同的类型获取的appid不一样
     * @param int $timeOut
     * @throws \Exception
     * @return \Exception 成功时返回，其他抛异常
     */
    public static function unifiedOrder($data, $type = "", $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //检测必填参数
        if (empty($data['out_trade_no'])) {
            throw new \Exception("缺少统一支付接口必填参数out_trade_no！");
        }
        if (empty($data["body"])) {
            throw new \Exception("缺少统一支付接口必填参数body！");
        }
        if (empty($data['total_fee'])) {
            throw new \Exception("缺少统一支付接口必填参数total_fee！");
        }
        if (empty($data['trade_type'])) {
            throw new \Exception("缺少统一支付接口必填参数trade_type！");
        }

        //关联参数
        if ($data["trade_type"] == "JSAPI" && empty($data["openid"])) {
            throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }
        if ($data['trade_type'] == "NATIVE" && empty($data['product_id'])) {
            throw new \Exception("统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！");
        }

        //异步通知url未设置，则使用配置文件中的url
        if (empty($data['notify_url']) && self::getConfig("notify_url") != "") {
            $data['notify_url'] = self::getConfig("notify_url");//异步通知url
        }

        if ($type == 'app') {
            $data["appid"] = self::getConfig("wxapp.app_id"); //APP应用ID
        } else {
            $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        }

        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["spbill_create_ip"] = $_SERVER['REMOTE_ADDR'];//终端ip
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        //签名
        $data["sign"] = self::getSign($data);
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);
    }

    /**
     * 下载对账单，WxPayDownloadBill中bill_date为必填参数
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param int $timeOut
     * @return mixed|string
     * @throws \Exception
     */
    public static function downloadBill(array $data, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/downloadbill";
        //检测必填参数
        if (empty($data["bill_date"])) {
            throw new \Exception("对账单接口中，缺少必填参数bill_date！");
        }
        $data["appid"] = self::getConfig("wxh5.appid");//公众账号ID
        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        if (substr($response, 0, 5) == "<xml>") {
            return "";
        }
        return $response;
    }

    /**
     * 提交被扫支付API
     * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
     * 由商户收银台或者商户后台调用该接口发起支付。
     * WxPayWxPayMicroPay中body、out_trade_no、total_fee、auth_code参数必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function micropay(array $data, $timeOut = 10)
    {
        $url = "https://api.mch.weixin.qq.com/pay/micropay";
        //检测必填参数
        if (empty($data["body"])) {
            throw new \Exception("提交被扫支付API接口中，缺少必填参数body！");
        }
        if (empty($data["out_trade_no"])) {
            throw new \Exception("提交被扫支付API接口中，缺少必填参数out_trade_no！");
        }
        if (empty($data["total_fee"])) {
            throw new \Exception("提交被扫支付API接口中，缺少必填参数total_fee！");
        }
        if (empty($data["auth_code"])) {
            throw new \Exception("提交被扫支付API接口中，缺少必填参数auth_code！");
        }

        $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["spbill_create_ip"] = $_SERVER['REMOTE_ADDR'];//终端ip
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);
    }

    /**
     * 撤销订单API接口，WxPayReverse中参数out_trade_no和transaction_id必须填写一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param int $timeOut
     * @return mixed
     * @throws \Exception
     */
    public static function reverse(array $data, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/reverse";
        //检测必填参数
        if (empty($data["out_trade_no"]) && empty($data["transaction_id"])) {
            throw new \Exception("撤销订单API接口中，参数out_trade_no和transaction_id必须填写一个！");
        }

        $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);

        $response = self::postXmlCurl($xml, $url, true, $timeOut);
        return self::response($response);

    }

    /**
     * 生成二维码规则,模式一生成支付二维码
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public static function bizpayurl(array $data)
    {
        if (empty($data["product_id"])) {
            throw new \Exception("生成二维码，缺少必填参数product_id！");
        }

        $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串
        $data["time_stamp"] = time();//时间戳

        $data["sign"] = self::getSign($data);//签名

        return $data;
    }

    /**
     * 转换短链接
     * 该接口主要用于扫码原生支付模式一中的二维码链接转成短链接(weixin://wxpay/s/XXXXXX)，
     * 减小二维码数据量，提升扫描速度和精确度。
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     *
     * @param array $data
     * @param int $timeOut
     * @return mixed
     * @throws Exception
     */
    public static function shorturl(array $data, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/tools/shorturl";
        //检测必填参数
        if (empty($data["long_url"])) {
            throw new Exception("需要转换的URL，签名用原串，传输需URL encode！");
        }

        $data["appid"] = self::getConfig("wxh5.app_id"); //公众账号ID
        $data["mch_id"] = self::getConfig("mch_id");//商户号
        $data["nonce_str"] = self::getNonceStr();//随机字符串

        $data["sign"] = self::getSign($data);//签名
        $xml = self::ToXml($data);


        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        return self::response($response);

    }

    /*************************************************工具方法********************************************************/

    /**
     * 产生随机字符串，不长于32位
     *
     * @param int $length
     * @return string 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 直接输出xml
     * @param string $xml
     */
    public static function replyNotify($xml)
    {
        echo $xml;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param $xml string 需要post的xml数据
     * @param $url string url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @return mixed
     * @throws \Exception
     */
    private static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        $curlVersion = curl_version();
        $ua = "WXPaySDK/" . self::$VERSION . " (" . PHP_OS . ") PHP/" . PHP_VERSION . " CURL/" . $curlVersion['version'] . " "
            . self::getConfig("mch_id");

        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            //证书文件请放入服务器的非web目录下
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, self::getConfig("cert_client"));
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, self::getConfig("cert_key"));
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);

        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        }

        $error = curl_errno($ch);
        curl_close($ch);
        throw new \Exception("curl出错，错误码:$error");

    }

    /**
     * 获取签名
     *
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private static function getSign(array $data)
    {
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = self::ToUrlParams($data);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . self::getConfig("key");
        //签名步骤三：MD5加密或者HMAC-SHA256
        $signType = self::getConfig("sign_type");
        if ($signType == "MD5") {
            $string = md5($string);
        } else if ($signType == "HMAC-SHA256") {
            $string = hash_hmac("sha256", $string, self::getConfig("key"));
        } else {
            throw new \Exception("签名类型不支持！");
        }

        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 验签
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    private static function checkSign(array $data)
    {
        if (empty($data['sign'])) {
            throw new \Exception("签名错误！");
        }

        $sign = self::getSign($data);
        if ($data["sign"] == $sign) {
            //签名正确
            return true;
        }
        throw new \Exception("签名错误！");
    }

    /**
     * 格式化参数格式化成url参数
     *
     * @param array $data
     * @return string
     */
    private static function ToUrlParams(array $data)
    {
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 格式化请求数据xml
     *
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public static function ToXml(array $data)
    {
        if (!is_array($data) || count($data) <= 0) {
            throw new \Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     *
     * @param $xml
     * @return mixed
     * @throws \Exception
     */
    private static function FromXml($xml)
    {
        if (!$xml) {
            throw new \Exception("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**
     * 获取返回结果并验证签名 将xml转为array
     *
     * @param $xml
     * @return mixed
     * @throws \Exception
     */
    private static function response($xml)
    {
        $data = self::FromXml($xml);
        //失败则直接返回失败
        if ($data['return_code'] != 'SUCCESS') {
            foreach ($data as $key => $value) {
                #除了return_code和return_msg之外其他的参数存在，则报错
                if ($key != "return_code" && $key != "return_msg") {
                    throw new \Exception("输入数据存在异常！");
                }
            }
            return $data;
        }
        self::checkSign($data);
        return $data;
    }

    /**
     * notify获取响应结果
     *
     * @param $xml
     * @return mixed
     */
    public static function notifyResponse($xml)
    {
        $data = self::FromXml($xml);
        //失败则直接返回失败
        self::checkSign($data);
        return $data;
    }
}

