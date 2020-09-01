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
    const QUERY_PARAMS_ERROR = [1000, "非法参数"];
    const QUERY_INVALID = [1001, "非法请求"];
    const TRY_AGAIN_LATER = [1002, "稍后重试"];

    const USER_NOT_LOGIN = [2000, "请先登录"];
    const USER_MOBILE_ERR = [2001, "请输入正确的手机号"];
    const USER_SEND_SMS_LIMIT = [2002, "短信触发小时级流控"];
    const USER_SEND_SMS_ERR = [2003, "短信发送失败"];
    const USER_VERIFY_CODE_ERR = [2004, "验证码错误"];
    const USER_USER_NUMBER_NOT_EXISTS = [2005, "用户编号不存在"];
    const USER_SEX_UNKNOWN = [2006, "请选择性别"];
    const USER_MODIFY_SEX_FORBIDDEN = [2007, "性别无法修改"];

    const DYNAMIC_CONTENT_EMPTY = [3001, "动态不能为空"];
    const DYNAMIC_NOT_EXISTS = [3002, "动态不存在"];

    const VIP_OFFLINE = [4000, "vip套餐已下架"];

    public static function factory($errConst, $message = "")
    {
        $code = $errConst[0];
        $message = $message ? $message : $errConst[1];
        $e = new self($message, $code);
        return $e;
    }
}