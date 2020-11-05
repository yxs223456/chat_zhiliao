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

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "否",
        ],
        self::YES => [
            "cn" => "是",
        ],
    ];
}