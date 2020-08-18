<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-30
 * Time: 19:06
 */

namespace app\common\enum;

/**
 * 通用流水类型
 */
class FlowTypeEnum
{
    use EnumTrait;

    const ADD = 1;
    const REDUCE = 2;


    protected static $desc = [
        self::ADD => [
            "cn" => "增加",
        ],
        self::REDUCE => [
            "cn" => "减少",
        ],
    ];
}