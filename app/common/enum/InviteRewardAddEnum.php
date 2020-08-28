<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-28
 * Time: 10:41
 */

namespace app\common\enum;

/**
 * 邀请奖励类型
 */
class InviteRewardAddEnum
{
    use EnumTrait;

    const MALE_REGISTER = 1;
    const MALE_CALL = 2;
    const MALE_RECHARGE = 3;
    const FEMALE_PRETTY = 4;
    const FEMALE_INCOME = 5;


    protected static $desc = [
        self::MALE_REGISTER => [
            "cn" => "男生注册",
        ],
        self::MALE_CALL => [
            "cn" => "男生通话3分钟",
        ],
        self::MALE_RECHARGE => [
            "cn" => "男生充值提成",
        ],
        self::FEMALE_PRETTY => [
            "cn" => "女生成为女神",
        ],
        self::FEMALE_INCOME => [
            "cn" => "女生收入提成",
        ],
    ];
}