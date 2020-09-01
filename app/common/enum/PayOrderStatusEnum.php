<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:41
 */

namespace app\common\enum;

/**
 * 微信支付订单类型
 */
class PayOrderStatusEnum
{
    use EnumTrait;

    const WAIT_PAY = 1;
    const PAY = 2;
    const PART_REFUND = 3;
    const ALL_REFUND = 4;

    protected static $desc = [
        self::WAIT_PAY => [
            "cn" => "未支付",
        ],
        self::PAY => [
            "cn" => "已支付",
        ],
        self::PART_REFUND => [
            "cn" => "部分退款",
        ],
        self::ALL_REFUND => [
            "cn" => "全部退款",
        ],
    ];
}