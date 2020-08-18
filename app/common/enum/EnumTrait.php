<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-03-04
 * Time: 15:41
 */

namespace app\common\enum;

trait EnumTrait
{
    static public function getAllList($lang = "cn")
    {
        $r = new \ReflectionClass(self::class);

        $constantsList = $r->getConstants();

        $list = [];

        foreach ($constantsList as $value) {
            $info = [
                "value" => $value,
                "desc" => self::$desc[$value][$lang],
            ];
            array_push($list, $info);
        }

        return $list;
    }

    static public function getEnumDescByValue($value, $lang = "cn")
    {
        if (isset(self::$desc[$value][$lang])) {
            return self::$desc[$value][$lang];
        }
        return "";
    }

    static public function getAllValues()
    {
        $r = new \ReflectionClass(self::class);

        $list = $r->getConstants();

        return $list;
    }
}