<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-12
 * Time: 16:55
 */

namespace app\common\enum;

/**
 * 提现状态
 */
class WithdrawStatusEnum
{
    use EnumTrait;

    const PROCESSING = 0;
    const SUCCESS = 1;
    const FAIL = 2;

    protected static $desc = [
        self::PROCESSING => [
            "cn" => "处理中",
        ],
        self::SUCCESS => [
            "cn" => "成功",
        ],
        self::FAIL => [
            "cn" => "失败",
        ],
    ];
}