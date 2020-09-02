<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:26
 */

namespace app\common\enum;

/**
 * 数据是否显示
 */
class IsShowEnum
{
    use EnumTrait;

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "不显示",
        ],
        self::YES => [
            "cn" => "正常显示",
        ],
    ];
}