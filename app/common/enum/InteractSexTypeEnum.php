<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 用户性别类型
 */
class InteractSexTypeEnum
{
    use EnumTrait;

    const UNKNOWN = 0;
    const MALE_TO_MALE = 11;
    const FEMALE_TO_FEMALE = 22;
    const MALE_TO_FEMALE = 12;
    const FEMALE_TO_MALE = 21;

    protected static $desc = [
        self::UNKNOWN => [
            "cn" => "未定义",
        ],
        self::MALE_TO_MALE => [
            "cn" => "男男",
        ],
        self::FEMALE_TO_FEMALE => [
            "cn" => "女女",
        ],
        self::MALE_TO_FEMALE => [
            "cn" => "男女"
        ],
        self::FEMALE_TO_MALE => [
            "cn" => "女男"
        ]
    ];
}