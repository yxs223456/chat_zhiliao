<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:49
 */

namespace app\common\enum;

/**
 * 通话类型
 */
class ChatTypeEnum
{
    use EnumTrait;

    const VIDEO = 1;
    const VOICE = 2;

    protected static $desc = [
        self::VIDEO => [
            "cn" => "视频通话",
        ],
        self::VOICE => [
            "cn" => "语音通话",
        ],
    ];
}