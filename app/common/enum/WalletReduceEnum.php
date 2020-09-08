<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 10:34
 */

namespace app\common\enum;

/**
 * 聊币消耗类型
 */
class WalletReduceEnum
{
    use EnumTrait;

    const GIFT = 1;
    const VIDEO_CHAT = 2;
    const VOICE_CHAT = 3;
    const RED_PACKAGE = 4;
    const SECRET_MESSAGE = 5;
    const WITHDRAW = 6;

    protected static $desc = [
        self::GIFT => [
            "cn" => "送礼物",
        ],
        self::VIDEO_CHAT => [
            "cn" => "视频聊天",
        ],
        self::VOICE_CHAT => [
            "cn" => "语音聊天",
        ],
        self::RED_PACKAGE => [
            "cn" => "发红包",
        ],
        self::SECRET_MESSAGE => [
            "cn" => "私聊收费",
        ],
        self::WITHDRAW => [
            "cn" => "提现",
        ],
    ];
}