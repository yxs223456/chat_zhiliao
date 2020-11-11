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
class AppVersionTypeEnum
{
    use EnumTrait;

    const ANDROID = 1;
    const IOS = 2;

    protected static $desc = [
        self::ANDROID => [
            "cn" => "安卓",
        ],
        self::IOS => [
            "cn" => "ios",
        ],
    ];
}