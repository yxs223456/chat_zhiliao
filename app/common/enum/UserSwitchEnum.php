<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 用户设置开启
 */
class UserSwitchEnum
{
    use EnumTrait;

    const OFF = 0;
    const ON = 1;

    protected static $desc = [
        self::OFF => [
            "cn" => "off",
        ],
        self::ON => [
            "cn" => "on",
        ],

    ];
}