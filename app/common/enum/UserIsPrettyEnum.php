<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-16
 * Time: 11:14
 */

namespace app\common\enum;

/**
 * 用户是否是男/女神
 */
class UserIsPrettyEnum
{
    use EnumTrait;

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "否",
        ],
        self::YES => [
            "cn" => "是",
        ]
    ];
}