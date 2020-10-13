<?php

namespace app\common\helper;

use think\facade\Log;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/5/6
 * Time: 下午6:10
 */
class AliPay
{
    //返回数据格式
    private static $format = "json";
    //api版本
    private static $apiVersion = "1.0";
    // 表单提交字符集编码
    private static $postCharset = "UTF-8";
    private static $fileCharset = "UTF-8";
    private static $RESPONSE_SUFFIX = "_response";
    private static $ERROR_RESPONSE = "error_response";
    private static $SIGN_NODE_NAME = "sign";


    private static $config = null; //配置文件


    const APP_METHOD = "alipay.trade.app.pay"; // app支付
    const WAP_METHOD = "alipay.trade.wap.pay"; // 手机网站支付

    const PAY = "alipay.trade.pay"; // 统一收单交易支付
    const PRE_CREATE = "alipay.trade.precreate"; // 统一收单线下交易预创建
    const PAGE = "alipay.trade.page.pay"; // 统一收单下单并支付页面接口
    const CREATE = "alipay.trade.create"; // 统一收单交易创建接口
    const QUERY = "alipay.trade.query"; // 统一收单线下交易查询
    const CANCEL = "alipay.trade.cancel"; // 统一收单交易撤销接口 ✅
    const CLOSE = "alipay.trade.close"; // 统一收单交易关闭接口 ✅
    const REFUND = "alipay.trade.refund"; // 统一收单交易退款接口 ✅
    const PAGE_REFUND = "alipay.trade.page.refund"; // 统一收单退款页面接口
    const REFUND_QUERY = "alipay.trade.fastpay.refund.query"; // 统一收单交易退款查询
    const SETTLE = "alipay.trade.order.settle"; // 统一收单交易结算接口

    /**
     * 获取配置参数
     *
     * @param $key
     * @return string
     */
    protected static function getConfig($key)
    {
        if (empty(self::$config)) {
            self::$config = config('account.alipay');
        }

        return isset(self::$config[$key]) ? self::$config[$key] : "";
    }

    /**
     * 支付宝h5支付
     *
     * @param $subject string 商品的标题/交易标题/订单标题/订单关键字等
     * @param $outTradeNo string 商户网站唯一订单号
     * @param $totalAmount float 金额 [0.01,100000000] (元)
     * @param $quitUrl string 用户付款中途退出返回商户网站的地址
     * @param $body string 对一笔交易的具体描述信息
     *
     * @return string
     * @throws \Exception
     */
    public static function h5($subject, $outTradeNo, $totalAmount, $quitUrl, $body = "")
    {
        if (empty($subject) || empty($outTradeNo) || empty($totalAmount) || empty($quitUrl)) {
            throw new \Exception("subject,out_trade_no,total_amount,quit_url 都不能为空");
        }
        $data = [
            'subject' => $subject,
            'out_trade_no' => $outTradeNo,
            'total_amount' => $totalAmount,
            'quit_url' => $quitUrl,
            'body' => $body
        ];

        return self::h5Pay(array_filter($data),"GET");
    }

    /**
     * 支付宝app支付
     *
     * @param $subject string 商品的标题/交易标题/订单标题/订单关键字等
     * @param $outTradeNo string 商户网站唯一订单号
     * @param $totalAmount float 金额 [0.01,100000000] (元)
     * @param $body string 对一笔交易的具体描述信息
     * @return string
     * @throws \Exception
     */
    public static function app($subject, $outTradeNo, $totalAmount, $body = '')
    {
        if (empty($subject) || empty($outTradeNo) || empty($totalAmount)) {
            throw new \Exception("subject,out_trade_no,total_amount 都不能为空");
        }
        $data = [
            'subject' => $subject,
            'out_trade_no' => $outTradeNo,
            'total_amount' => $totalAmount,
            'body' => $body
        ];
        return self::appPay(array_filter($data));
    }

    /**
     * 查询订单
     *
     * @param $outTradeNo
     * @param string $tradeNo
     * @return bool|mixed|\SimpleXMLElement|string
     * @throws \Exception
     */
    public static function orderQuery($outTradeNo, $tradeNo = '')
    {
        if (empty($outRequestNo) && empty($tradeNo)) {
            throw new \Exception("out_trade_no 和 trade_no 必须有一个不为空");
        }
        $data = [
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
        ];
        return self::execute(array_filter($data), self::QUERY);
    }

    /**
     * 退款接口
     *
     * @param $outTradeNo string 商户订单号
     * @param $tradeNo string 支付宝交易号
     * @param $refundAmount float 退款金额
     * @param string $outRequestNo 多次退款使用
     * @param string $refundReason 退款说明
     * @return bool|mixed|\SimpleXMLElement|string
     * @throws \Exception
     */
    public static function refund($outTradeNo, $refundAmount, $tradeNo = '', $outRequestNo = '', $refundReason = '')
    {

        if (empty($outRequestNo) && empty($tradeNo)) {
            throw new \Exception("out_trade_no 和 trade_no 必须有一个不为空");
        }
        if (empty($refundAmount)) {
            throw new \Exception("退款金额refund_amount不能为空");
        }
        $data = [
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
            'refund_amount' => $refundAmount,
            'refund_reason' => $refundReason,
            'out_request_no' => $outRequestNo,
        ];
        return self::execute(array_filter($data), self::REFUND);
    }

    /**
     * 退款查询
     *
     * @param $outTradeNo string 商户订单号
     * @param $outRequestNo string
     * @param string $tradeNo string
     * @return bool|mixed|\SimpleXMLElement|string
     * @throws \Exception
     */
    public static function refundQuery($outTradeNo, $outRequestNo, $tradeNo = '')
    {
        if (empty($outRequestNo) && empty($tradeNo)) {
            throw new \Exception("out_trade_no 和 trade_no 必须有一个不为空");
        }
        if (empty($outRequestNo)) {
            throw new \Exception("out_request_mo不能为空");
        }
        $data = [
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
            'out_request_no' => $outRequestNo,
        ];
        return self::execute(array_filter($data), self::REFUND_QUERY);
    }

    /**
     * 关闭订单接口
     *
     * @param $outTradeNo string 商户订单号
     * @param string $tradeNo string 支付宝交易号
     * @return bool|mixed|\SimpleXMLElement|string
     * @throws \Exception
     */
    public static function closeOrder($outTradeNo, $tradeNo = '')
    {
        if (empty($outRequestNo) && empty($tradeNo)) {
            throw new \Exception("out_trade_no 和 trade_no 必须有一个不为空");
        }
        $data = [
            'out_trade_no' => $outTradeNo,
            'trade_no' => $tradeNo,
        ];
        return self::execute(array_filter($data), self::CLOSE);
    }

    /**
     * 支付结果通用通知
     * @throws \Exception
     */
    public static function notify()
    {
        if ($_POST["app_id"] != self::getConfig("app_id")) {
            throw new \Exception("app_id 验证失败");
        }
        if ($_POST["seller_id"] != self::getConfig("seller_id")) {
            throw new \Exception("seller_id 验证失败");
        }
        if (!self::checkSign($_POST)) {
            throw new \Exception("签名验证失败");
        }
    }

    /****************************************************工具方法******************************************************/

    /**
     * app支付
     *
     * @param array $data 业务数据
     * @return string
     */
    private static function appPay(array $data)
    {
        self::setupCharsets($data);

        $params['app_id'] = self::getConfig("app_id");
        $params['method'] = self::APP_METHOD;
        $params['format'] = self::$format;
        $params['sign_type'] = self::getConfig("sign_type");
        $params['timestamp'] = date("Y-m-d H:i:s");
        $params['charset'] = self::$postCharset;
        $params['version'] = self::$apiVersion;
        $params['notify_url'] = self::getConfig("notify_url");

        $data['product_code'] = 'QUICK_MSECURITY_PAY';
        $params['biz_content'] = json_encode($data);

        ksort($params);

        $params['sign'] = self::generateSign($params, self::getConfig("sign_type"));

        foreach ($params as &$value) {
            $value = self::characet($value, $params['charset']);
        }

        return http_build_query($params);
    }

    /**
     * h5 支付 页面提交执行方法
     *
     * @param array $data
     * @param string $httpmethod 跳转类接口的request; $httpmethod 提交方式。两个值可选：post、get
     * @return string 构建好的、签名后的最终跳转URL（GET）或String形式的form（POST）
     * @throws \Exception
     */
    private static function h5Pay(array $data, $httpmethod = "GET")
    {
        self::setupCharsets($data);

        if (strcasecmp(self::$fileCharset, self::$postCharset)) {

            // writeLog("本地文件字符集编码与表单提交编码不一致，请务必设置成一样，属性名分别为postCharset!");
            throw new \Exception("文件编码：[" . self::$fileCharset . "] 与表单提交编码：[" . self::$postCharset . "]两者不一致!");
        }

        //组装系统参数
        $sysParams["app_id"] = self::getConfig("app_id");
        $sysParams["version"] = self::$apiVersion;
        $sysParams["format"] = self::$format;
        $sysParams["sign_type"] = self::getConfig("sign_type");
        $sysParams["method"] = self::WAP_METHOD;
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["notify_url"] = self::getConfig("notify_url");
        $sysParams["return_url"] = self::getConfig("return_url");
        $sysParams["charset"] = self::$postCharset;

        //获取业务参数
        $data['product_code'] = 'QUICK_WAP_WAY';
        $sysParams['biz_content'] = json_encode($data);

        //待签名字符串
        $preSignStr = self::getSignContent($sysParams);

        //签名
        $sysParams["sign"] = self::generateSign($sysParams, self::getConfig("sign_type"));

        if ("GET" == $httpmethod) {

            //拼接GET请求串
            $requestUrl = self::getConfig("gateway_url") . "?" . $preSignStr . "&sign=" . urlencode($sysParams["sign"]);

            return $requestUrl;
        } else {
            //拼接表单字符串
            return self::buildRequestForm($sysParams);
        }
    }

    /**
     * 公共请求方法
     *
     * @param array $data
     * @param string $apiName
     * @param null $authToken
     * @param null $appInfoAuthtoken
     * @return bool|mixed|\SimpleXMLElement
     * @throws \Exception
     */
    private static function execute(array $data, $apiName, $authToken = null, $appInfoAuthtoken = null)
    {

        self::setupCharsets($data);

        //		//  如果两者编码不一致，会出现签名验签或者乱码
        if (strcasecmp(self::$fileCharset, self::$postCharset)) {

            // writeLog("本地文件字符集编码与表单提交编码不一致，请务必设置成一样，属性名分别为postCharset!");
            throw new \Exception("文件编码：[" . self::$fileCharset . "] 与表单提交编码：[" . self::$postCharset . "]两者不一致!");
        }

        //组装系统参数
        $sysParams["app_id"] = self::getConfig("app_id");
        $sysParams["version"] = self::$apiVersion;
        $sysParams["format"] = self::$format;
        $sysParams["sign_type"] = self::getConfig("sign_type");
        $sysParams["method"] = $apiName;
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["auth_token"] = $authToken;
        $sysParams["notify_url"] = self::getConfig("notify_url");
        $sysParams["charset"] = self::$postCharset;
        $sysParams["app_auth_token"] = $appInfoAuthtoken;

        //获取业务参数
        $sysParams['biz_content'] = json_encode($data);

        //签名
        $sysParams["sign"] = self::generateSign($sysParams, self::getConfig("sign_type"));

        //系统参数放入GET请求串
        $requestUrl = self::getConfig("gateway_url") . "?";
        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode(self::characet($sysParamValue, self::$postCharset)) . "&";
        }
        $requestUrl = substr($requestUrl, 0, -1);

        //发起HTTP请求
        try {
            $resp = self::curl($requestUrl, $data);
        } catch (\Exception $e) {
            Log::error("[Alipay execute error] : " . $sysParams["method"] . " " . json_encode($data) . " " . $e->getMessage());
            return false;
        }

        //解析AOP返回结果
        $respWellFormed = false;


        // 将返回结果转换本地文件编码
        $r = iconv(self::$postCharset, self::$fileCharset . "//IGNORE", $resp);

        $signData = null;

        if ("json" == self::$format) {

            $respObject = json_decode($r);
            if (null !== $respObject) {
                $respWellFormed = true;
                $signData = self::parserJSONSignData($apiName, $resp, $respObject);
            }
        } else if ("xml" == self::$format) {

            $respObject = @simplexml_load_string($resp);
            if (false !== $respObject) {
                $respWellFormed = true;

                $signData = self::parserXMLSignData($apiName, $resp);
            }
        }

        //返回的HTTP文本不是标准JSON或者XML，记下错误日志
        if (false === $respWellFormed) {
            Log::error("[Alipay execute error] : " . $sysParams["method"] . " " . $requestUrl . " " . $resp);
            return false;
        }

        // 验签
        self::checkResponseSign($apiName, $signData, $resp, $respObject);

        return $respObject;
    }

    /**
     * 获取签名方法
     *
     * @param $params
     * @param string $signType
     * @return string
     */
    private static function generateSign($params, $signType = "RSA")
    {
        return self::sign(self::getSignContent($params), $signType);
    }

    /**
     * 获取签名拼接字符串
     *
     * @param $params
     * @return string
     */
    private static function getSignContent($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === self::checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = self::characet($v, self::$postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 签名加密
     *
     * @param $data
     * @param string $signType
     * @return string
     */
    private static function sign($data, $signType = "RSA")
    {
        if (self::checkEmpty(self::getConfig("private_key_file_path"))) {
            $priKey = self::getConfig('private_key');
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        } else {
            $priKey = file_get_contents(self::getConfig('private_key_file_path'));
            $res = openssl_get_privatekey($priKey);
        }

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        if (!self::checkEmpty(self::getConfig('private_key_file_path'))) {
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * request 方法
     *
     * @param $url
     * @param null $postFields
     * @return mixed
     * @throws \Exception
     */
    private static function curl($url, $postFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $postBodyString = "";
        $encodeArray = Array();
        $postMultipart = false;


        if (is_array($postFields) && 0 < count($postFields)) {

            foreach ($postFields as $k => $v) {
                if ("@" != substr($v, 0, 1)) //判断是不是文件上传
                {

                    $postBodyString .= "$k=" . urlencode(self::characet($v, self::$postCharset)) . "&";
                    $encodeArray[$k] = self::characet($v, self::$postCharset);
                } else //文件上传用multipart/form-data，否则用www-form-urlencoded
                {
                    $postMultipart = true;
                    $encodeArray[$k] = new \CURLFile(substr($v, 1));
                }

            }
            unset ($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeArray);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
            }
        }

        if ($postMultipart) {

            $headers = array('content-type: multipart/form-data;charset=' . self::$postCharset . ';boundary=' . self::getMillisecond());
        } else {

            $headers = array('content-type: application/x-www-form-urlencoded;charset=' . self::$postCharset);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        $reponse = curl_exec($ch);

        if (curl_errno($ch)) {

            throw new \Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new \Exception($reponse, $httpStatusCode);
            }
        }

        curl_close($ch);
        return $reponse;
    }

    private static function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }


    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp array 请求参数数组
     * @return string 提交表单HTML文本
     */
    protected static function buildRequestForm($para_temp)
    {

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . self::getConfig("gateway_url") . "?charset=" . trim(self::$postCharset) . "' method='POST'>";
        while (list ($key, $val) = each($para_temp)) {
            if (false === self::checkEmpty($val)) {
                //$val = $this->characet($val, self::$postCharset);
                $val = str_replace("'", "&apos;", $val);
                //$val = str_replace("\"","&quot;",$val);
                $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
            }
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml . "<input type='submit' value='ok' style='display:none;''></form>";

        $sHtml = $sHtml . "<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    private static function characet($data, $targetCharset)
    {

        if (!empty($data)) {
            $fileType = self::$fileCharset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }


        return $data;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     *
     * @param $value
     * @return bool
     */
    protected static function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    private static function verify($data, $sign, $signType = 'RSA')
    {
        if (self::checkEmpty(self::getConfig('ali_public_key_file_path'))) {
            $pubKey = self::getConfig("ali_public_key");
            $res = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($pubKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        } else {
            //读取公钥文件
            $pubKey = file_get_contents(self::getConfig('ali_public_key_file_path'));
            //转换为openssl格式密钥
            $res = openssl_get_publickey($pubKey);
        }

        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');

        //调用openssl内置方法验签，返回bool值

        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }

        if (!self::checkEmpty(self::getConfig('ali_public_key_file_path'))) {
            //释放资源
            openssl_free_key($res);
        }

        return $result;
    }

    private static function parserResponseSubCode($apiName, $responseContent, $respObject, $format)
    {

        if ("json" == $format) {

            $rootNodeName = str_replace(".", "_", $apiName) . self::$RESPONSE_SUFFIX;
            $errorNodeName = self::$ERROR_RESPONSE;

            $rootIndex = strpos($responseContent, $rootNodeName);
            $errorIndex = strpos($responseContent, $errorNodeName);

            if ($rootIndex > 0) {
                // 内部节点对象
                $rInnerObject = $respObject->$rootNodeName;
            } elseif ($errorIndex > 0) {

                $rInnerObject = $respObject->$errorNodeName;
            } else {
                return null;
            }

            // 存在属性则返回对应值
            if (isset($rInnerObject->sub_code)) {

                return $rInnerObject->sub_code;
            } else {

                return null;
            }


        } elseif ("xml" == $format) {

            // xml格式sub_code在同一层级
            return $respObject->sub_code;
        }

        return null;
    }

    private static function parserJSONSignData($apiName, $responseContent, $responseJSON)
    {

        $signData = array();

        $signData['sign'] = self::parserJSONSign($responseJSON);
        $signData['signSourceData'] = self::parserJSONSignSource($apiName, $responseContent);


        return $signData;

    }

    private static function parserJSONSignSource($apiName, $responseContent)
    {

        $rootNodeName = str_replace(".", "_", $apiName) . self::$RESPONSE_SUFFIX;

        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, self::$ERROR_RESPONSE);


        if ($rootIndex > 0) {

            return self::parserJSONSource($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return self::parserJSONSource($responseContent, self::$ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }

    private static function parserJSONSource($responseContent, $nodeName, $nodeIndex)
    {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 2;
        $signIndex = strpos($responseContent, "\"" . self::$SIGN_NODE_NAME . "\"");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;
        $indexLen = $signDataEndIndex - $signDataStartIndex;
        if ($indexLen < 0) {

            return null;
        }

        return substr($responseContent, $signDataStartIndex, $indexLen);

    }

    private static function parserJSONSign($responseJSon)
    {
        return $responseJSon->sign;
    }

    private static function parserXMLSignData($apiName, $responseContent)
    {
        $signData = array();

        $signData['sign'] = self::parserXMLSign($responseContent);
        $signData['signSourceData'] = self::parserXMLSignSource($apiName, $responseContent);

        return $signData;
    }

    private static function parserXMLSignSource($apiName, $responseContent)
    {

        $rootNodeName = str_replace(".", "_", $apiName) . self::$RESPONSE_SUFFIX;


        $rootIndex = strpos($responseContent, $rootNodeName);
        $errorIndex = strpos($responseContent, self::$ERROR_RESPONSE);
        //		$this->echoDebug("<br/>rootNodeName:" . $rootNodeName);
        //		$this->echoDebug("<br/> responseContent:<xmp>" . $responseContent . "</xmp>");


        if ($rootIndex > 0) {

            return self::parserXMLSource($responseContent, $rootNodeName, $rootIndex);
        } else if ($errorIndex > 0) {

            return self::parserXMLSource($responseContent, self::$ERROR_RESPONSE, $errorIndex);
        } else {

            return null;
        }


    }

    private static function parserXMLSource($responseContent, $nodeName, $nodeIndex)
    {
        $signDataStartIndex = $nodeIndex + strlen($nodeName) + 1;
        $signIndex = strpos($responseContent, "<" . self::$SIGN_NODE_NAME . ">");
        // 签名前-逗号
        $signDataEndIndex = $signIndex - 1;
        $indexLen = $signDataEndIndex - $signDataStartIndex + 1;

        if ($indexLen < 0) {
            return null;
        }


        return substr($responseContent, $signDataStartIndex, $indexLen);


    }

    private static function parserXMLSign($responseContent)
    {
        $signNodeName = "<" . self::$SIGN_NODE_NAME . ">";
        $signEndNodeName = "</" . self::$SIGN_NODE_NAME . ">";

        $indexOfSignNode = strpos($responseContent, $signNodeName);
        $indexOfSignEndNode = strpos($responseContent, $signEndNodeName);


        if ($indexOfSignNode < 0 || $indexOfSignEndNode < 0) {
            return null;
        }

        $nodeIndex = ($indexOfSignNode + strlen($signNodeName));

        $indexLen = $indexOfSignEndNode - $nodeIndex;

        if ($indexLen < 0) {
            return null;
        }

        // 签名
        return substr($responseContent, $nodeIndex, $indexLen);

    }

    /**
     * 验签
     * @param $apiName
     * @param $signData
     * @param $resp
     * @param $respObject
     * @throws \Exception
     */
    public static function checkResponseSign($apiName, $signData, $resp, $respObject)
    {

        if (!self::checkEmpty(self::getConfig('ali_public_key_file_path')) || !self::checkEmpty(self::getConfig('ali_public_key'))) {


            if (empty($signData) || self::checkEmpty($signData['sign']) || self::checkEmpty($signData['signSourceData'])) {

                throw new \Exception(" check sign Fail! The reason : signData is Empty");
            }


            // 获取结果sub_code
            $responseSubCode = self::parserResponseSubCode($apiName, $resp, $respObject, self::$format);


            if (!self::checkEmpty($responseSubCode) || (self::checkEmpty($responseSubCode) && !self::checkEmpty($signData['sign']))) {

                $checkResult = self::verify($signData['signSourceData'], $signData['sign'], self::getConfig("sign_type"));


                if (!$checkResult) {

                    if (strpos($signData['signSourceData'], "\\/") > 0) {

                        $signData['signSourceData'] = str_replace("\\/", "/", $signData['signSourceData']);

                        $checkResult = self::verify($signData['signSourceData'], $signData['sign'], self::getConfig("sign_type"));

                        if (!$checkResult) {
                            throw new \Exception("check sign Fail! [sign=" . $signData['sign'] . ", signSourceData=" . $signData['signSourceData'] . "]");
                        }

                    } else {

                        throw new \Exception("check sign Fail! [sign=" . $signData['sign'] . ", signSourceData=" . $signData['signSourceData'] . "]");
                    }

                }
            }


        }
    }

    private static function setupCharsets($request)
    {
        if (self::checkEmpty(self::$postCharset)) {
            self::$postCharset = 'UTF-8';
        }
        $str = preg_match('/[\x80-\xff]/', self::getConfig("app_id")) ? self::getConfig("app_id") : print_r($request, true);
        self::$fileCharset = mb_detect_encoding($str, "UTF-8, GBK") == 'UTF-8' ? 'UTF-8' : 'GBK';
    }

    /**
     * 验证签名
     *
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public static function checkSign($params)
    {
        if (empty($params['sign'])) {
            throw new \Exception("签名错误");
        }
        $sign = $params['sign'];
        $params['sign'] = null;
        return self::verify(self::getSignContent($params), $sign, self::getConfig("sign_type"));
    }

}