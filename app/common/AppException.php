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
    const QUERY_PARAMS_LOSS_SIGN = [1000, "缺少参数sign"];
    const QUERY_PARAMS_LOSS_APP_ID = [1001, "缺少参数app_id"];
    const QUERY_PARAMS_SIGN_ERR = [1002, "sign验证失败"];
    const QUERY_PARAMS_INVALID_APP_ID = [1003, "app_id无效"];
    const QUERY_PARAMS_ERROR = [1004, "参数错误"];

    const PAY_ORDER_NO_EXISTS = [2000, "订单号已存在"];
    const PAY_ORDER_NO_NOT_EXISTS = [2001, "订单号不存在"];

    const WITHDRAW_BALANCE_LESS = [3000, "账户余额不足"];

    const WALLET_NOT_EXISTS = [4000, "未找到账号钱包信息"];

    public static function factory($errConst, $message = "")
    {
        $code = $errConst[0];
        $message = $message ? $message : $errConst[1];
        $e = new self($message, $code);
        return $e;
    }
}