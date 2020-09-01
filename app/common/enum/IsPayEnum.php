<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:26
 */

namespace app\common\enum;

/**
 * 支付订单是否支付
 */
class IsPayEnum
{
    use EnumTrait;

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "未支付",
        ],
        self::YES => [
            "cn" => "已支付",
        ],
    ];
}