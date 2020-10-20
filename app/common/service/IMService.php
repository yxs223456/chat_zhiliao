<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-14
 * Time: 16:07
 */
namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\FlowTypeEnum;
use app\common\enum\UserSwitchEnum;
use app\common\enum\WalletAddEnum;
use app\common\enum\WalletReduceEnum;
use app\common\helper\Redis;
use think\facade\Db;

class IMService extends Base
{
    /**
     * 判断是否可以发送私聊
     * @param $user
     * @param $tUId
     * @return \stdClass
     * @throws \Throwable
     */
    public function checkSendMessage($user, $tUId)
    {
        $redis = Redis::factory();

        // 消息接收者拉黑用户时无法发送
        if (BlackListService::inUserBlackList($tUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 消息接收者私聊免费无需处理
        $tUSet = UserSetService::getUserSetByUId($tUId, $redis);
        if ($tUSet["direct_message_free"] == UserSwitchEnum::OFF) {
            return new \stdClass();
        }
        $price = $tUSet["direct_message_price"];
        $bonus = (int) round($price * Constant::MESSAGE_BONUS_RATE);

        // 消息接收者私聊收费判断消息发送者余额
        Db::startTrans();
        try {
            $uWallet = Db::name("user_wallet")->where("u_id", $user["id"])->lock(true)->find();
            if ($uWallet["total_balance"] < $tUSet["direct_message_price"]) {
                throw AppException::factory(AppException::WALLET_MONEY_LESS);
            }

            // 余额充足，纪录相关数据库操作
            // 扣除发送者余额
            if ($uWallet["balance_amount"] >= $price) {
                Db::name("user_wallet")->where("id", $uWallet["id"])
                    ->dec("balance_amount", $price)
                    ->dec("total_balance", $price)
                    ->update();
            } else {
                Db::name("user_wallet")->where("id", $uWallet["id"])
                    ->dec("balance_amount", $uWallet["balance_amount"])
                    ->dec("income_amount", $price - $uWallet["balance_amount"])
                    ->dec("total_balance", $price)
                    ->update();
            }

            // 增加接收者余额
            $tUWallet = Db::name("user_wallet")->where("u_id", $tUId)->find();
            Db::name("user_wallet")->where("id", $tUWallet["id"])
                ->inc("income_amount", $bonus)
                ->inc("total_balance", $bonus)
                ->update();

            // 纪录发送者、接收者余额流水
            $uWalletFlowData = [
                "u_id" => $user["id"],
                "flow_type" => FlowTypeEnum::REDUCE,
                "amount" => $price,
                "add_type" => 0,
                "reduce_type" => WalletReduceEnum::DIRECT_MESSAGE,
                "object_source_id" => $tUId,
                "before_balance" => $uWallet["total_balance"],
                "after_balance" => $uWallet["total_balance"] - $price,
                "create_date" => date("Y-m-d"),
            ];
            $tUWalletFlowData = [
                "u_id" => $tUId,
                "flow_type" => FlowTypeEnum::ADD,
                "amount" => $bonus,
                "add_type" => WalletAddEnum::DIRECT_MESSAGE,
                "reduce_type" => 0,
                "object_source_id" => $user["id"],
                "before_balance" => $tUWallet["total_balance"],
                "after_balance" => $tUWallet["total_balance"] + $bonus,
                "create_date" => date("Y-m-d"),
            ];
            Db::name("user_wallet_flow")->insertAll([$uWalletFlowData, $tUWalletFlowData]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return new \stdClass();
    }
}