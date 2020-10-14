<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-30
 * Time: 19:06
 */

namespace app\common\enum;

/**
 * 通用流水类型
 */
class FollowIsFriendEnum
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
        ],
    ];
}