<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:49
 */

namespace app\common\enum;

/**
 * 通话状态
 */
class ChatStatusEnum
{
    use EnumTrait;

    const WAIT_ANSWER = 0;
    const CALLING = 1;
    const END = 2;
    const NO_ANSWER = 3;

    protected static $desc = [
        self::WAIT_ANSWER => [
            "cn" => "待接听",
        ],
        self::CALLING => [
            "cn" => "通话中",
        ],
        self::END => [
            "cn" => "通话结束",
        ],
        self::NO_ANSWER => [
            "cn" => "未接听",
        ],
    ];
}