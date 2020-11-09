<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 11:26
 */

namespace app\common\enum;

/**
 * 小视频是否转换完成
 */
class VideoIsTransCodeEnum
{
    use EnumTrait;

    const TRANSCODING = 0; //转码中
    const SUCCESS = 1; // 成功
    const ERROR = 2;// 失败

    protected static $desc = [
        self::TRANSCODING => [
            "cn" => "转码中",
        ],
        self::SUCCESS => [
            "cn" => "成功",
        ],
        self::ERROR => [
            "cn" => "失败",
        ],
    ];
}