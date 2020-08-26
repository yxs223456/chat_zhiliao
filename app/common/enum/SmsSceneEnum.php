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

    protected static $desc = [
        self::LOGIN => [
            "cn" => "登录",
        ],
    ];
}