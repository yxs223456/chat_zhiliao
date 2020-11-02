<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-02
 * Time: 15:25
 */

namespace app\common\enum;

/**
 * 礼物是否上架
 */
class ImMessageTypeEnum
{
    use EnumTrait;

    const MESSAGE = 1;
    const IMAGE = 2;
    const GIFT = 3;
    const SOUND = 4;
    const RED_PACKAGE = 5;

    protected static $desc = [
        self::MESSAGE => [
            "cn" => "文字",
        ],
        self::IMAGE => [
            "cn" => "图片",
        ],
        self::GIFT => [
            "cn" => "礼物",
        ],
        self::SOUND => [
            "cn" => "语言",
        ],
        self::RED_PACKAGE => [
            "cn" => "红包",
        ],
    ];
}