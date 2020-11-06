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
use app\common\helper\LinePay;
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
                "out_trade_no" => $outTradeNo,
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

    /**
     * line pay 购买vip套餐
     * @param $user
     * @param $id
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function payBylinePay($user, $id)
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
        $currency = config("app.currency");

        //纪录数据库
        Db::startTrans();
        try {
            Db::name("pay_order_line")->insertGetId([
                "u_id" => $user["id"],
                "out_trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "amount" => $money,
                "currency" => $currency,
            ]);

            Db::name("pay_order")->insert([
                "u_id" => $user["id"],
                "scene" => PayOrderSceneEnum::VIP,
                "source_id" => $id,
                "out_trade_no" => $outTradeNo,
                "is_pay" => IsPayEnum::NO,
                "pay_method" => PayMethodEnum::LINE_PAY,
                "amount" => $money,
                "currency" => $currency,
                "status" => PayOrderStatusEnum::WAIT_PAY,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        // line pay 发起支付请求
        $cancelUrl = "https://chat.admin.local.com/admin/index/index.html";
        $response = LinePay::requestApi($money, $outTradeNo, $goodsName, $cancelUrl, $currency);
        $returnData = [
            "payment_access_token" => $response["info"]["paymentAccessToken"],
            "app_pay_url" => $response["info"]["paymentUrl"]["app"],
            "web_pay_url" => $response["info"]["paymentUrl"]["web"],
        ];

        return $returnData;
    }

    /**
     * 支付宝购买vip套餐
     * @param $user
     * @param $id
     * @return string
     * @throws AppException
     * @throws \Throwable
     */
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
                "out_trade_no" => $outTradeNo,
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
     * 购买vip套餐支付后续处理
     * @param $userId
     * @param $vipId
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function afterPay($userId, $vipId)
    {
        $vip = Db::name("config_vip")->where("id", $vipId)->find();
        $userInfo = Db::name("user_info")->where("u_id", $userId)->lock(true)->find();

        /**
         * 延迟用户vip套餐时间
         * vip套餐规则：先消耗svip时长，再消耗vip时长
         */
        // 套餐vip延迟时间（单位秒）
        $vipValidTime = self::computeVipDay($vip["valid_time"]);

        // 先处理svip逻辑
        if ($vip["vip_type"] == VipTypeEnum::SVIP) {
            if ($userInfo["svip_deadline"] && $userInfo["svip_deadline"] >= date("Y-m-d")) {
                $newSvipDeadline = date("Y-m-d", strtotime($userInfo["svip_deadline"])+$vipValidTime);
            } else {
                $newSvipDeadline = date("Y-m-d", time()+$vipValidTime-86400);
            }
        } else {
            $newSvipDeadline = $userInfo["svip_deadline"];
        }

        if ($userInfo["vip_deadline"] && $userInfo["vip_deadline"] >= date("Y-m-d")) {
            $newVipDeadline = date("Y-m-d", strtotime($userInfo["vip_deadline"])+$vipValidTime);
        } elseif ($vip["vip_type"] == VipTypeEnum::VIP) {
            $newVipDeadline = date("Y-m-d", time()+$vipValidTime-86400);
        } else {
            $newVipDeadline = $userInfo["vip_deadline"];
        }
        Db::name("user_info")->where("id", $userInfo["id"])->update([
            "svip_deadline" => $newSvipDeadline,
            "vip_deadline" => $newVipDeadline,
        ]);
        // 删除用户info缓存
        deleteUserInfoDataByUId($userId, Redis::factory());
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
                $day = $num * 86400;
                break;
            case "m":
                $day = $num * 30 * 86400;
                break;
            case "q":
                $day = $num * 90 * 86400;
                break;
            case "y":
                $day = $num * 360 * 86400;
                break;
        }
        return $day;
    }

    /**
     *  判断用户是否是VIP
     *
     * @param $userId
     * @return bool
     */
    public static function isVip($userId)
    {
        $userInfo = UserInfoService::getUserInfoById($userId, Redis::factory());
        $today = date("Y-m-d");
        $vipDeadline = empty($userInfo["vip_deadline"]) ? date("Y-m-d", strtotime("-1 day")) : $userInfo["vip_deadline"];
        $svipDeadline = empty($userInfo['svip_deadline']) ? date("Y-m-d", strtotime("-1 day")) : $userInfo["svip_deadline"];
        // vip过期 不是vip不能设置通话聊天金额
        if ($today > $vipDeadline && $today > $svipDeadline) {
            return false;
        }
        return true;
    }
}