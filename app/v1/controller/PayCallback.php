<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-28
 * Time: 15:14
 */

namespace app\v1\controller;

use app\BaseController;
use app\common\enum\IsPayEnum;
use app\common\enum\PayOrderSceneEnum;
use app\common\enum\PayOrderStatusEnum;
use app\common\helper\AliPay;
use app\common\helper\WechatPay;
use app\common\service\VipService;
use think\facade\Db;

class PayCallback extends BaseController
{
    /**
     * 微信支付回调
     */
    public function weChatCallback()
    {
        // 接收微信支付回调通知，并验证
        $weChatNotice = WechatPay::notify();

        // 支付后续处理
        Db::startTrans();
        try {
            /**
             * 微信支付订单处理
             */
            $wxPayOrder = Db::name("pay_order_wx")
                ->where("out_trade_no", $weChatNotice["out_trade_no"])
                ->lock(true)
                ->find();
            // 判断是否已处理（非常重要）
            if ($wxPayOrder["is_pay"] == IsPayEnum::YES) {
                throw new \Exception("微信支付订单已处理". json_encode($weChatNotice, JSON_UNESCAPED_UNICODE));
            }
            // 判断订单金额是否对应
            if ($weChatNotice["total_fee"] != bcmul($wxPayOrder["amount"], 100)) {
                throw new \Exception("支付金额不对应:" . json_encode($weChatNotice, JSON_UNESCAPED_UNICODE));
            }
            // 将微信支付订单修改为已支付状态
            Db::name("pay_order_wx")
                ->where("id", $wxPayOrder["id"])
                ->update([
                    "is_pay" => IsPayEnum::YES,
                    "transaction_id" => $weChatNotice["transaction_id"],
                    "notify_data" => json_encode($weChatNotice, JSON_UNESCAPED_UNICODE),
                ]);

            /**
             * 用户支付订单处理
             */
            $userPayOrder = Db::name("pay_order")
                ->where("out_trade_no", $weChatNotice["out_trade_no"])
                ->find();
            // 将用户支付订单修改为已支付状态
            Db::name("pay_order")
                ->where("id", $userPayOrder["id"])
                ->update([
                    "is_pay" => IsPayEnum::YES,
                    "pay_time" => strtotime($weChatNotice["time_end"]),
                    "status" => PayOrderStatusEnum::PAY,
                ]);

            /**
             * 根据支付场景，进行后续处理
             */
            switch ($userPayOrder["scene"]) {
                case PayOrderSceneEnum::VIP:
                    VipService::afterPay($userPayOrder["u_id"], $userPayOrder["source_id"]);
                    break;
            }


            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return <<<XML
<xml>
  <return_code><![CDATA[SUCCESS]]></return_code>
  <return_msg><![CDATA[OK]]></return_msg>
</xml>
XML;

    }

    /**
     * 支付宝支付回调
     */
    public function AliCallback()
    {
        // 验证回调通知
        AliPay::notify();

        // 只处理支付情况下的回调
        $aliNotice = $_POST;
        if (in_array($aliNotice["trade_status"], ["TRADE_SUCCESS", "TRADE_FINISHED"])) {
            // 支付后续处理
            Db::startTrans();
            try {
                /**
                 * 阿里支付订单处理
                 */
                $aliPayOrder = Db::name("pay_order_ali")
                    ->where("out_trade_no", $aliNotice["out_trade_no"])
                    ->lock(true)
                    ->find();
                // 判断是否已处理（非常重要）
                if ($aliPayOrder["is_pay"] == IsPayEnum::YES) {
                    throw new \Exception("支付宝支付订单已处理". json_encode($aliNotice, JSON_UNESCAPED_UNICODE));
                }
                // 判断订单金额是否对应
                if (bccomp($aliNotice["total_amount"], $aliPayOrder["amount"], 2) != 0) {
                    throw new \Exception("支付金额不对应:" . json_encode($aliNotice, JSON_UNESCAPED_UNICODE));
                }
                // 将微信支付订单修改为已支付状态
                Db::name("pay_order_ali")
                    ->where("id", $aliPayOrder["id"])
                    ->update([
                        "is_pay" => IsPayEnum::YES,
                        "trade_no" => $aliPayOrder["trade_no"],
                        "notify_data" => json_encode($aliNotice, JSON_UNESCAPED_UNICODE),
                    ]);

                /**
                 * 用户支付订单处理
                 */
                $userPayOrder = Db::name("pay_order")
                    ->where("out_trade_no", $aliNotice["out_trade_no"])
                    ->find();
                // 将用户支付订单修改为已支付状态
                Db::name("pay_order")
                    ->where("id", $userPayOrder["id"])
                    ->update([
                        "is_pay" => IsPayEnum::YES,
                        "pay_time" => strtotime($aliNotice["gmt_payment"]),
                        "status" => PayOrderStatusEnum::PAY,
                    ]);

                /**
                 * 根据支付场景，进行后续处理
                 */
                switch ($userPayOrder["scene"]) {
                    case PayOrderSceneEnum::VIP:
                        VipService::afterPay($userPayOrder["u_id"], $userPayOrder["source_id"]);
                        break;
                }

                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }
        }

        return "success";
    }
}