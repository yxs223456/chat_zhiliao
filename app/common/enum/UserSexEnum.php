<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 用户性别
 */
class UserSexEnum
{
    use EnumTrait;

    const UNKNOWN = 0;
    const MALE = 1;
    const FEMALE = 2;

    protected static $desc = [
        self::UNKNOWN => [
            "cn" => "未定义",
        ],
        self::MALE => [
            "cn" => "男",
        ],
        self::FEMALE => [
            "cn" => "nv",
        ],
    ];
}