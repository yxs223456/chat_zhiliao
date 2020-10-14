<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 10:34
 */

namespace app\common\enum;

/**
 * 聊币增加类型
 */
class WalletAddEnum
{
    use EnumTrait;

    const RECHARGE = 1;
    const TASK_LOTTERY = 2;
    const GIFT = 3;
    const VIDEO_CHAT = 4;
    const VOICE_CHAT = 5;
    const RED_PACKAGE = 6;
    const DIRECT_MESSAGE = 7;
    const ANGEL = 8;

    protected static $desc = [
        self::RECHARGE => [
            "cn" => "充值",
        ],
        self::TASK_LOTTERY => [
            "cn" => "任务抽奖",
        ],
        self::GIFT => [
            "cn" => "收礼物",
        ],
        self::VIDEO_CHAT => [
            "cn" => "视频聊天",
        ],
        self::VOICE_CHAT => [
            "cn" => "语音聊天",
        ],
        self::RED_PACKAGE => [
            "cn" => "收红包",
        ],
        self::DIRECT_MESSAGE => [
            "cn" => "付费私聊",
        ],
        self::ANGEL => [
            "cn" => "守护者分润",
        ],
    ];
}