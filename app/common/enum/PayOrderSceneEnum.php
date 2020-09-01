<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:33
 */

namespace app\common\enum;

/**
 * 支付订单场景
 */
class PayOrderSceneEnum
{
    use EnumTrait;

    const COIN = 1;
    const VIP = 2;

    protected static $desc = [
        self::COIN => [
            "cn" => "充值聊币",
        ],
        self::VIP => [
            "cn" => "充值vip",
        ],
    ];
}