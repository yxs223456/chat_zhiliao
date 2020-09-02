<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-02
 * Time: 10:23
 */

namespace app\common\enum;

/**
 * banner 位置
 */
class BannerPositionEnum
{
    use EnumTrait;

    const APP_HOME = 1;

    protected static $desc = [
        self::APP_HOME => [
            "cn" => "APP首页",
        ],
    ];
}