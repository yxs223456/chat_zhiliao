<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/18
 * Time: 17:44
 */

namespace app\common;

class AppException extends \Exception
{
    const QUERY_PARAMS_ERROR = [1000, "非法请求"];
    const QUERY_INVALID = [1001, "非法请求"];

    const USER_NOT_LOGIN = [2000, "请先登录"];
    const USER_MOBILE_ERR = [2001, "请输入正确的手机号"];
    const USER_MESSAGE = [2002, "短信触发小时级流控"];
    const USER_SMS_ERR = [2003, "短信发送失败"];

    public static function factory($errConst, $message = "")
    {
        $code = $errConst[0];
        $message = $message ? $message : $errConst[1];
        $e = new self($message, $code);
        return $e;
    }
}