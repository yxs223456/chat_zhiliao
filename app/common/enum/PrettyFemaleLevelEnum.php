<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 女神级别
 */
class PrettyFemaleLevelEnum
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
            "cn" => "普通女性",
        ],
        self::TRAINEE => [
            "cn" => "见习女神",
        ],
        self::IRON => [
            "cn" => "铁牌女神",
        ],
        self::COPPER => [
            "cn" => "铜牌女神",
        ],
        self::SILVER => [
            "cn" => "银牌女神",
        ],
        self::GOLD => [
            "cn" => "金牌女神",
        ],
        self::CROWN => [
            "cn" => "皇冠女神",
        ],
    ];
}