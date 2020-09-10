<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 男神级别
 */
class PrettyMaleLevelEnum
{
    use EnumTrait;

    const COMMON = 0;
    const TRAINEE = 1;
    const IRON = 2;
    const COPPER = 3;
    const SILVER = 4;
    const GOLD = 5;
    const CROWN = 6;

    protected static $desc = [
        self::COMMON => [
            "cn" => "普通男性VIP",
        ],
        self::TRAINEE => [
            "cn" => "见习男神VIP",
        ],
        self::IRON => [
            "cn" => "铁牌男神VIP",
        ],
        self::COPPER => [
            "cn" => "铜牌男神VIP",
        ],
        self::SILVER => [
            "cn" => "银牌男神VIP",
        ],
        self::GOLD => [
            "cn" => "金牌男神VIP",
        ],
        self::CROWN => [
            "cn" => "皇冠女神VIP",
        ],
    ];
}