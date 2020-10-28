<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:39
 */

namespace app\common\enum;

/**
 * 支付方式
 */
class PayMethodEnum
{
    use EnumTrait;

    const ALI = 1;
    const WE_CHAT = 2;
    const LINE_PAY = 3;

    protected static $desc = [
        self::ALI => [
            "cn" => "支付宝",
        ],
        self::WE_CHAT => [
            "cn" => "微信",
        ],
        self::LINE_PAY => [
            "cn" => "line pay",
        ],
    ];
}