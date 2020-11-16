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
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\FlowTypeEnum;
use app\common\enum\IsPayEnum;
use app\common\enum\PayMethodEnum;
use app\common\enum\PayOrderSceneEnum;
use app\common\enum\PayOrderStatusEnum;
use app\common\enum\RechargePackageIsSaleEnum;
use app\common\enum\WalletAddEnum;
use app\common\enum\WalletReduceEnum;
use app\common\enum\WeChatPayOrderTypeEnum;
use app\common\enum\WithdrawStatusEnum;
use app\common\helper\AliPay;
use app\common\helper\LinePay;
use app\common\helper\WechatPay;
use app\common\model\UserWalletFlowModel;
use think\facade\Db;

class WalletService extends Base
{
    /**
     * 获取充值规格数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function rechargePackage()
    {
        return Db::name("config_coin")
            ->field("id,coin_price,price")
            ->where("is_sale", RechargePackageIsSaleEnum::YES)
            ->where("is_delete", DbDataIsDeleteEnum::NO)
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
    public function rechargeByWeChat($rechargeId, $user)
    {
        $rechargeConfig = Db::name("config_coin")->where("id", $rechargeId)->find();
        if (empty($rechargeConfig)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($rechargeConfig["is_delete"] == DbDataIsDeleteEnum::YES ||
            $rechargeConfig["is_sale"] == RechargePackageIsSaleEnum::NO) {
            throw AppException::factory(AppException::WALLET_RECHARGE_PACKAGE_OFFLINE); // 判断是否下架
        }

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
    public function rechargeByAli($rechargeId, $user)
    {
        $rechargeConfig = Db::name("config_coin")->where("id", $rechargeId)->find();
        if (empty($rechargeConfig)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($rechargeConfig["is_delete"] == DbDataIsDeleteEnum::YES ||
            $rechargeConfig["is_sale"] == RechargePackageIsSaleEnum::NO) {
            throw AppException::factory(AppException::WALLET_RECHARGE_PACKAGE_OFFLINE); // 判断是否下架
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
                "scene" => PayOrderSceneEnum::COIN,
                "source_id" => $rechargeId,
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
        $quitUrl = config("web.coin_recharge_url");
        $returnData = AliPay::h5($goodsName, $outTradeNo, $money, $quitUrl);

        return $returnData;
    }

    /**
     * line pay 支付聊币
     *
     * @param $rechargeId
     * @param $user
     * @return array
     * @throws AppException
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function rechargeByLine($rechargeId, $user)
    {
        $rechargeConfig = Db::name("config_coin")->where("id", $rechargeId)->find();
        if (empty($rechargeConfig)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($rechargeConfig["is_delete"] == DbDataIsDeleteEnum::YES ||
            $rechargeConfig["is_sale"] == RechargePackageIsSaleEnum::NO) {
            throw AppException::factory(AppException::WALLET_RECHARGE_PACKAGE_OFFLINE); // 判断是否下架
        }


        /**
         * 生成支付订单，先记录数据库，后向line pay下单
         */
        $goodsName = "充值";
        $outTradeNo = "RECHARGE" . date("ymdHis") . getRandomString(10);
        $money = $rechargeConfig["price"]; // vip套餐价格单位元
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
                "scene" => PayOrderSceneEnum::COIN,
                "source_id" => $rechargeId,
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
     * 提现页面信息
     *
     * @param $user
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function withdrawInfo($user)
    {
        $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->find();

        $incomeAmount = $userWallet["income_amount"];
        $withdrawAmount = bcmul($userWallet["income_amount"], config("app.coin_exchange_rate"), 2);

        $returnData = [
            "income_amount" => $incomeAmount,
            "money_amount" => $withdrawAmount,
        ];
        return $returnData;
    }

    /**
     * 提现
     *
     * @param $amount
     * @param $user
     * @return \stdClass
     * @throws \Throwable
     */
    public function withdraw($amount, $user)
    {

        Db::startTrans();
        try {
            // 判断可提现金额
            $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->lock(true)->find();
            if ($userWallet["income_amount"] < $amount) {
                throw AppException::factory(AppException::WALLET_MONEY_LESS);
            }

            // todo 纪录提现操作纪录

            // todo 纪录提现纪录
            $outTradeNo = "WITHDRAW" . date("ymdhis") . getRandomString(10);
            $withdrawMoney = bcmul($amount, config("app.coin_exchange_rate"), 2);
            $currency = config("app.currency");
            $withdrawId = Db::name("user_withdraw_log")->insertGetId([
                "u_id" => $user["id"],
                "out_trade_no" => $outTradeNo,
                "coin_amount" => $amount,
                "money_amount" => $withdrawMoney,
                "currency" => $currency,
                "method" => "", // todo 提现方式
                "source_id" => "", // todo 提现方式id
                "status" => WithdrawStatusEnum::PROCESSING,
            ]);

            // 减少余额
            Db::name("user_wallet")->where("id", $userWallet["id"])
                ->dec("income_amount", $amount)
                ->dec("total_balance", $amount)
                ->update();

            // 纪录钱包流水
            UserWalletFlowModel::reduceFlow(
                $user["id"],
                $amount,
                WalletReduceEnum::WITHDRAW,
                $withdrawId,
                $userWallet["total_balance"],
                $userWallet["total_balance"]-$amount
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return new \stdClass();
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
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function afterPay($userId, $rechargeId)
    {
        $wallet = Db::name("user_wallet")->where("u_id", $userId)->lock(true)->find();
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