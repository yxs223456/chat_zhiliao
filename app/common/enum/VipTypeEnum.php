<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:49
 */

namespace app\common\enum;

/**
 * vip类型
 */
class VipTypeEnum
{
    use EnumTrait;

    const VIP = 1;
    const SVIP = 2;

    protected static $desc = [
        self::VIP => [
            "cn" => "vip",
        ],
        self::SVIP => [
            "cn" => "svip",
        ],
    ];
}