<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-30
 * Time: 19:06
 */

namespace app\common\enum;

/**
 * 举报类型
 */
class DynamicReportTypeEnum
{
    use EnumTrait;

    const VULGAR = 1;
    const FRAUD = 2;
    const HARASS = 3;
    const ADVERTISING = 4;
    const USE_OTHER_PHOTO = 5;
    const RELIGION = 6;
    const OTHER = 7;


    protected static $desc = [
        self::VULGAR => [
            "cn" => "色情低俗",
            "tw" => "色情低俗"
        ],
        self::FRAUD => [
            "cn" => "欺诈骗钱",
            "tw" => "欺詐騙錢"
        ],
        self::HARASS => [
            "cn" => "侮辱诋毁",
            "tw" => "侮辱詆毀"
        ],
        self::ADVERTISING => [
            "cn" => "拉人广告",
            "tw" => "拉人廣告"
        ],
        self::USE_OTHER_PHOTO => [
            "cn" => "盗用他人照片",
            "tw" => "盜用他人照片"
        ],
        self::RELIGION => [
            "cn" => "政治宗教",
            "tw" => "政治宗教"
        ],
        self::OTHER => [
            "cn" => "其他",
            "tw" => "其他"
        ],

    ];
}