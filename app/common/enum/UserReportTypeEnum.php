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
class UserReportTypeEnum
{
    use EnumTrait;

    const VULGAR = 1;
    const FRAUD = 2;
    const HARASS = 3;
    const NOT_ONESELF = 4;
    const ADVERTISING = 5;
    const INDUCTION_TRADE = 6;
    const NONAGE = 7;
    const RELIGION = 8;
    const ABUSE = 9;
    const GENDER_FRAUD = 10;
    const OTHER = 11;


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
            "cn" => "骚扰威胁",
            "tw" => "騷擾威脅"
        ],
        self::NOT_ONESELF => [
            "cn" => "照片非本人",
            "tw" => "照片非本人"
        ],
        self::ADVERTISING => [
            "cn" => "拉人广告",
            "tw" => "拉人廣告"
        ],
        self::INDUCTION_TRADE => [
            "cn" => "诱导三方平台交易",
            "tw" => "誘導三方平台交易"
        ],
        self::NONAGE => [
            "cn" => "未成年",
            "tw" => "未成年"
        ],
        self::RELIGION => [
            "cn" => "政治宗教",
            "tw" => "政治宗教"
        ],
        self::ABUSE => [
            "cn" => "恶意辱骂",
            "tw" => "惡意辱罵"
        ],
        self::GENDER_FRAUD => [
            "cn" => "性别造假",
            "tw" => "性别造假"
        ],
        self::OTHER => [
            "cn" => "其他",
            "tw" => "其他"
        ],

    ];
}