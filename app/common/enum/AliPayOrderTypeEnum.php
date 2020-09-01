<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:49
 */

namespace app\common\enum;

/**
 * 支付宝支付订单类型
 */
class AliPayOrderTypeEnum
{
    use EnumTrait;

    const APP = 1;
    const H5 = 2;

    protected static $desc = [
        self::APP => [
            "cn" => "APP",
        ],
        self::H5 => [
            "cn" => "H5",
        ],
    ];
}