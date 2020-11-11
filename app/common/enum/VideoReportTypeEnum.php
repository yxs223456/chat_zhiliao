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
class VideoReportTypeEnum
{
    use EnumTrait;

    const USE_OTHER_VIDEO = 1;
    const RELIGION = 2;
    const OTHER = 3;


    protected static $desc = [
        self::USE_OTHER_VIDEO => [
            "cn" => "盗用他人视频",
            "tw" => "盜用他人視頻"
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