<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:08
 */

namespace app\common\service;

use app\common\enum\VipTypeEnum;
use app\common\helper\Redis;
use app\common\model\ConfigVipModel;
use app\common\model\UserInfoModel;

class VipService extends Base
{
    public function home($user)
    {
        $returnData = [
            "user" => [],
            "svip" => [],
            "vip" => [],
        ];

        $userInfoModel = new UserInfoModel();
        $userInfo = $userInfoModel->findByUId($user["id"]);
        $isSvip = empty($userInfo["svip_deadline"]) ? 0 : (int) $userInfo["svip_deadline"] >= date("Y-m-d");
        $isVip = empty($userInfo["vip_deadline"]) ? 0 : (int) $userInfo["vip_deadline"] >= date("Y-m-d");
        $returnData["user"] = [
            "portrait" => $userInfo["portrait"],
            "nickname" => $userInfo["nickname"],
            "sex" => $user["sex"],
            "is_svip" => $isSvip,
            "is_vip" => $isVip,
            "svip_deadline" => $userInfo["svip_deadline"],
            "vip_deadline" => $userInfo["vip_deadline"],
        ];

        $vipConfig = $this->vipConfig();
        foreach ($vipConfig as $item) {
            if ($item["vip_type"] == VipTypeEnum::SVIP) {
                $returnData["vip"][] = [
                    "name" => $item["name"],
                    "origin_price" => $item["origin_price"],
                    "price" => $item["price"],
                    "valid_time_desc" => $item["valid_time_desc"],
                    "is_hot" => $item["is_hot"],
                ];
            } elseif ($item["vip_type"] == VipTypeEnum::VIP) {
                $returnData["svip"][] = [
                    "name" => $item["name"],
                    "origin_price" => $item["origin_price"],
                    "price" => $item["price"],
                    "valid_time_desc" => $item["valid_time_desc"],
                    "is_hot" => $item["is_hot"],
                ];
            }
        }

        return $returnData;
    }

    // vip套餐
    protected function vipConfig($redis = null)
    {
        if ($redis == null) {
            $redis = Redis::factory();
        }

        $vipConfig = getVipConfigByCache($redis);
        if (empty($vipConfig)) {
            $configVipModel = new ConfigVipModel();
            $vipConfig = $configVipModel->getAll();
            cacheVipConfig($vipConfig, $redis);
        }

        return $vipConfig;
    }

    public static function computeVipDay($vipValidTime) :int
    {
        $unit = substr($vipValidTime, -1);
        $num = substr($vipValidTime, 0, -1);
        $day = 0;

        switch ($unit) {
            case "d":
                $day = $num;
                break;
            case "m":
                $day = $num * 30;
                break;
            case "q":
                $day = $num * 90;
                break;
            case "y":
                $day = $num * 360;
                break;
        }
        return $day;
    }
}