<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 短信使用场景
 */
class SmsSceneEnum
{
    use EnumTrait;

    const LOGIN = 1;
    const REGISTER = 2;

    protected static $desc = [
        self::LOGIN => [
            "cn" => "登录",
        ],
        self::REGISTER => [
            "cn" => "注册",
        ],
    ];
}