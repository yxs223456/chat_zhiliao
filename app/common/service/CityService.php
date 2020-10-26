<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-26
 * Time: 18:21
 */

namespace app\common\service;

use app\common\helper\Redis;
use think\facade\Db;

class CityService extends Base
{
    public static function getCityByCode($cityCode, $lang = "zh-tw")
    {
        $redis = Redis::factory();
        $cityConfig = getCityConfigByCache($redis);
        if (empty($cityConfig)) {
            $cities = Db::name("config_city")->field("code,zh_cn,zh_tw")->select();
            $cityConfig = [];
            foreach ($cities as $city) {
                $cityConfig[$city["code"]] = [
                    "zh_cn" => $city["zh_cn"],
                    "zh_tw" => $city["zh_tw"],
                ];
            }
            cacheCityConfig($cityConfig, $redis);
        }

        switch ($lang) {
            case "zh-cn":
                return $cityConfig[$cityCode]["zh_cn"];
            default :
                return $cityConfig[$cityCode]["zh_tw"];
        }
    }
}