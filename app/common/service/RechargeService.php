<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午4:06
 */

namespace app\common\service;


use app\common\AppException;
use app\common\enum\AliPayOrderTypeEnum;
use app\common\enum\FlowTypeEnum;
use app\common\enum\IsPayEnum;
use app\common\enum\PayMethodEnum;
use app\common\enum\PayOrderSceneEnum;
use app\common\enum\PayOrderStatusEnum;
use app\common\enum\WalletAddEnum;
use app\common\enum\WeChatPayOrderTypeEnum;
use app\common\helper\AliPay;
use app\common\helper\WechatPay;
use think\facade\Db;

class RechargeService extends Base
{
    /**
     * 获取充值规格数据
     *
     * @return array
     */
    public function index()
    {
        return Db::name("config_coin")
            ->field("id,coin_price,price,is_new,is_wechat,is_alipay,gaving")
            ->order("sort")
            ->select()->toArray();
    }

    /**
     * 微信充值
     * 1. 创建订单
     * 2. 调用接口
     *
     * @param $rechargeId
     * @param $user
     * @return mixed
     * @throws AppException
     * @throws \Throwable
     */
    public function payByWeChat($rechargeId, $user)
    {
        $rechargeConfig = Db::name("config_coin")->where("id", $rechargeId)->find();
        if (empty($rechargeConfig)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        // 判断当前规格是否允许微信支付
        if (!$rechargeConfig["is_wechat"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // TODO 判断是否是新用户


        $goodsName = "充值";
        $outTradeNo = "RECHARGE" . date("ymdHis") . getRandomString(10);
        $money = $rechargeConfig["price"];
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
                "scene" => PayOrderSceneEnum::COIN,
                "source_id" => $rechargeId,
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
                "wap_url" => config("web.coin_recharge_url"),
                "wap_name" => config("app.app_name"),
            ],
        ];
        $returnData["mweb_url"] = WechatPay::h5($goodsName, $outTradeNo, $totalFee, $sceneInfo);

        return $returnData;
    }

    /**
     * 支付宝支付聊币
     *
     * @param $rechargeId
     * @param $user
     * @return string
     * @throws AppException
     * @throws \Throwable
     */
    public function payByAli($rechargeId, $user)
    {
        $rechargeConfig = Db::name("config_coin")->where("id", $rechargeId)->find();
        if (empty($rechargeConfig)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        // 判断当前规格是否允许支付宝支付
        if (!$rechargeConfig["is_alipay"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $goodsName = "充值";
        $outTradeNo = "RECHARGE" . date("ymdHis") . getRandomString(10);
        $money = $rechargeConfig["price"];

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
                "source_id" => $rechargeId,
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
        $quitUrl = config("web.coin_recharge_url");
        $returnData = AliPay::h5($goodsName, $outTradeNo, $money, $quitUrl);

        return $returnData;
    }

    /**
     * 充值支付回调处理
     * 1.添加钱包余额
     * 2.添加流水
     * 3.添加充值记录
     *
     * @param $userId
     * @param $rechargeId
     * @throws AppException
     */
    public static function afterPay($userId, $rechargeId)
    {
        $wallet = Db::name("user_wallet")->where("u_id", $userId)->force(true)->find();
        $rechargeConfig = Db::name("config_coin")->where('id', $rechargeId)->find();
        if (empty($wallet)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }

        // 添加充值记录
        $id = Db::name("user_recharge_coin_log")->insertGetId([
            'u_id' => $userId,
            'coin_package_id' => $rechargeId,
            'coin_price' => $rechargeConfig['coin_price'],
            'create_date' => date("Y-m-d")
        ]);

        // 添加流水
        Db::name("user_wallet_flow")->insert([
            'u_id' => $userId,
            'flow_type' => FlowTypeEnum::ADD,
            'amount' => $rechargeConfig['coin_price'],
            'add_type' => WalletAddEnum::RECHARGE,
            'object_source_id' => $id,
            'before_balance' => $wallet['total_balance'],
            'after_balance' => $wallet['total_balance'] + $rechargeConfig['coin_price'],
            'create_date' => date("Y-m-d")
        ]);

        // 更新钱包金额
        Db::name("user_wallet")->where("u_id", $userId)
            ->update([
                'balance_amount' => Db::raw("balance_amount+" . $rechargeConfig["coin_price"]),
                'recharge_amount' => Db::raw("recharge_amount+" . $rechargeConfig['coin_price']),
                'total_balance' => Db::raw("total_balance+" . $rechargeConfig["coin_price"])
            ]);
    }
}