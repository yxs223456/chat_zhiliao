<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:49
 */

namespace app\common\enum;

/**
 * vip套餐是否上架
 */
class VipIsSaleEnum
{
    use EnumTrait;

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "下架",
        ],
        self::YES => [
            "cn" => "上架",
        ],
    ];
}