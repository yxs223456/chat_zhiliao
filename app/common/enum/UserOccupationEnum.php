<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-25
 * Time: 14:21
 */

namespace app\common\enum;

/**
 * 用户职位
 */
class UserOccupationEnum
{
    use EnumTrait;

    const STUDENT = 1;
    const WORKER = 2;
    const FREELANCE = 3;
    const BOSS = 4;

    protected static $desc = [
        self::STUDENT => [
            "cn" => "学生",
        ],
        self::WORKER => [
            "cn" => "上班族",
        ],
        self::FREELANCE => [
            "cn" => "自由职业",
        ],
        self::BOSS => [
            "cn" => "老板"
        ]
    ];
}