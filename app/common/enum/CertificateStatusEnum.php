<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-25
 * Time: 11:40
 */

namespace app\common\enum;

/**
 * 自拍认证状态
 */
class CertificateStatusEnum
{
    use EnumTrait;

    const NONE = 0;
    const WAIT_AUDIT = 1;
    const FAIL = 2;
    const SUCCESS = 3;

    protected static $desc = [
        self::NONE => [
            "cn" => "未提交认证",
        ],
        self::WAIT_AUDIT => [
            "cn" => "待审核",
        ],
        self::FAIL => [
            "cn" => "认证不通过",
        ],
        self::SUCCESS => [
            "cn" => "认证通过",
        ],
    ];
}