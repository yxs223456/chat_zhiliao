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

    public static function factory($errConst, $message = "")
    {
        $code = $errConst[0];
        $message = $message ? $message : $errConst[1];
        $e = new self($message, $code);
        return $e;
    }
}