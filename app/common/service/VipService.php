<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:08
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\AliPayOrderTypeEnum;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\IsPayEnum;
use app\common\enum\PayMethodEnum;
use app\common\enum\PayOrderSceneEnum;
use app\common\enum\PayOrderStatusEnum;
use app\common\enum\VipIsSaleEnum;
use app\common\enum\VipTypeEnum;
use app\common\enum\WeChatPayOrderTypeEnum;
use app\common\helper\AliPay;
use app\common\helper\Redis;
use app\common\helper\WechatPay;
use app\common\model\ConfigVipModel;
use app\common\model\UserInfoModel;
use think\facade\Db;

class VipService extends Base
{
    /**
     * vip 模块首页
     * @param $user
     * @return array
     */
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
            "svip_deadline" => $isSvip ? $userInfo["svip_deadline"] : "",
            "vip_deadline" => $isVip ? $userInfo["vip_deadline"] : "",
        ];

        $vipConfig = $this->vipConfig();
        foreach ($vipConfig as $item) {
            if ($item["vip_type"] == VipTypeEnum::SVIP) {
                $returnData["vip"][] = [
                    "id" => $item["id"],
                    "name" => $item["name"],
                    "origin_price" => $item["origin_price"],
                    "price" => $item["price"],
                    "valid_time_desc" => $item["valid_time_desc"],
                    "is_hot" => $item["is_hot"],
                ];
            } elseif ($item["vip_type"] == VipTypeEnum::VIP) {
                $returnData["svip"][] = [
                    "id" => $item["id"],
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

    /**
     * 微信购买vip套餐
     * @param $user
     * @param $id
     * @return mixed
     * @throws AppException
     * @throws \Throwable
     */
    public function payByWeChat($user, $id)
    {
        // vip 套餐
        $vipConfigModel = new ConfigVipModel();
        $vip = $vipConfigModel->findById($id);
        if (empty($vip)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        if ($vip["is_delete"] == DbDataIsDeleteEnum::YES || $vip["is_sale"] == VipIsSaleEnum::NO) {
            throw AppException::factory(AppException::VIP_OFFLINE); // 判断是否下架
        }

        /**
         * 生成微信支付订单，先记录数据库，后向微信下单
         */
        $goodsName = ($vip["type"] == VipTypeEnum::VIP ? "vip充值-" : "svip充值-") . $vip["name"];
        $outTradeNo = ($vip["type"] == VipTypeEnum::VIP ? "VIP" : "SVIP") .
            date("ymdHis") . getRandomString(10);
        $money = $vip["price"]; // vip套餐价格单位元
        //纪录数据库
        Db::startTrans();
        try {
                Db::name("pay_order_wx")->insertGetId([
                "u_id" => $user["id"],
                "out_trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "type" => WeChatPayOrderTypeEnum::H5,
                "amount" => $money,
            ]);

            Db::name("pay_order")->insert([
                "u_id" => $user["id"],
                "scene" => PayOrderSceneEnum::VIP,
                "source_id" => $id,
                "trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "pay_method" => PayMethodEnum::WE_CHAT,
                "amount" => $money,
                "status" => PayOrderStatusEnum::WAIT_PAY,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        // 向微信请求
        $totalFee = bcmul($money, 100);
        $sceneInfo = [
            "h5_info" => [
                "type" => "Wap",
                "wap_url" => config("web.vip_recharge_url"),
                "wap_name" => config("app.app_name"),
            ],
        ];
        $returnData["mweb_url"] = WechatPay::h5($goodsName, $outTradeNo, $totalFee, $sceneInfo);

        return $returnData;
    }

    public function payByAli($user, $id)
    {
        // vip 套餐
        $vipConfigModel = new ConfigVipModel();
        $vip = $vipConfigModel->findById($id);
        if (empty($vip)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        if ($vip["is_delete"] == DbDataIsDeleteEnum::YES || $vip["is_sale"] == VipIsSaleEnum::NO) {
            throw AppException::factory(AppException::VIP_OFFLINE); // 判断是否下架
        }

        /**
         * 生成支付宝支付订单，先记录数据库，后向支付宝下单
         */
        $goodsName = ($vip["type"] == VipTypeEnum::VIP ? "vip充值-" : "svip充值-") . $vip["name"];
        $outTradeNo = ($vip["type"] == VipTypeEnum::VIP ? "VIP" : "SVIP") .
            date("ymdHis") . getRandomString(10);
        $money = $vip["price"]; // vip套餐价格单位元
        //纪录数据库
        Db::startTrans();
        try {
            Db::name("pay_order_ali")->insertGetId([
                "u_id" => $user["id"],
                "out_trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "type" => AliPayOrderTypeEnum::H5,
                "amount" => $money,
            ]);

            Db::name("pay_order")->insert([
                "u_id" => $user["id"],
                "scene" => PayOrderSceneEnum::VIP,
                "source_id" => $id,
                "trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "pay_method" => PayMethodEnum::ALI,
                "amount" => $money,
                "status" => PayOrderStatusEnum::WAIT_PAY,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        // 向支付宝请求
        $quitUrl = config("web.vip_recharge_url");
        $returnData = AliPay::h5($goodsName, $outTradeNo, $money, $quitUrl);

        return $returnData;
    }

    /**
     * 计算vip套餐时间 返回计算后的天数
     * @param $vipValidTime
     * @return int
     */
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