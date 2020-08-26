<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-02-28
 * Time: 17:38
 */
namespace app\common\enum;

/**
 * 数据库数据是否被删除
 */
class DbDataIsDeleteEnum
{
    use EnumTrait;

    const NO = 0;
    const YES = 1;

    protected static $desc = [
        self::NO => [
            "cn" => "有效",
        ],
        self::YES => [
            "cn" => "被删除",
        ],
    ];
}