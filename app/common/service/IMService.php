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
use app\common\enum\ChatStatusEnum;
use app\common\enum\FlowTypeEnum;
use app\common\enum\ImMessageTypeEnum;
use app\common\enum\UserSwitchEnum;
use app\common\enum\WalletAddEnum;
use app\common\enum\WalletReduceEnum;
use app\common\helper\Redis;
use app\common\helper\ShengWang;
use app\common\model\ChatModel;
use app\common\model\UserIncomeLogModel;
use app\common\model\UserSpendLogModel;
use think\facade\Db;
use think\facade\Log;

class IMService extends Base
{
    /**
     * 非通话发送私聊消息
     * @param $user
     * @param $tUId
     * @param $message
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function sendMessage($user, $tUId, $message)
    {
        // 不能和自己聊天
        if ($tUId == $user["id"]) {
            throw AppException::factory(AppException::IM_SEND_SELF);
        }

        $redis = Redis::factory();

        // 消息接收者拉黑用户时无法发送
        if (BlackListService::inUserBlackList($tUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 消息接收者私聊免费无需处理
        $tUSet = UserSetService::getUserSetByUId($tUId, $redis);
        if ($tUSet["direct_message_free"] == UserSwitchEnum::OFF) {
            $isFree = 1;
            $price = 0;
            $bonus = 0;
        } else {
            $isFree = 0;
            $price = $tUSet["direct_message_price"];
            $bonusRate = Constant::MESSAGE_BONUS_RATE;
            $bonus = (int) round($price * $bonusRate);
            $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
            $tUInfo = UserInfoService::getUserInfoById($tUId, $redis);

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
                    ->inc("income_total_amount", $bonus)
                    ->inc("total_balance", $bonus)
                    ->update();

                // 纪录发送者、接收者余额流水
                $uLogMsg = (config("app.api_language")=="zh-tw")?
                    "向 ".$tUInfo["nickname"]." 發送消息":
                    "向 ".$tUInfo["nickname"]." 发送消息";
                $rULogMsg = (config("app.api_language")=="zh-tw")?
                    "接收 ".$userInfo["nickname"]." 的消息":
                    "接收 ".$userInfo["nickname"]." 的消息";
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
                    "log_msg" => $uLogMsg,
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
                    "log_msg" => $rULogMsg,
                ];
                Db::name("user_wallet_flow")->insertAll([$uWalletFlowData, $tUWalletFlowData]);

                // 添加支出纪录
                UserSpendLogModel::addLog(
                    $user["id"],
                    $price,
                    WalletReduceEnum::DIRECT_MESSAGE,
                    $tUId,
                    $uLogMsg
                );

                // 添加收入纪录
                UserIncomeLogModel::addLog(
                    $tUId,
                    $bonus,
                    WalletAddEnum::DIRECT_MESSAGE,
                    $tUId,
                    $rULogMsg,
                    $bonusRate
                );

                Db::commit();

                // 计算魅力值放入队列
                userGuardCallbackProduce($tUId, $user["id"], $bonus, Redis::factory());

            } catch (\Throwable $e) {
                Db::rollback();
                throw $e;
            }
        }

        if (is_array($message)) {
            $message["price"] = $price;
            $message["r_u_income"] = $bonus;
        } else {
            $message = json_decode($message, true);
            $message["price"] = $price;
            $message["r_u_income"] = $bonus;
        }
        $message = json_encode($message);
        $response = ShengWang::sendMessage($user["id"], $tUId, $message);
        Log::write(json_encode($response));

        return [
            "is_free" => $isFree,
            "price" => $price,
            "r_u_income" => $bonus,
        ];
    }

    /**
     * 通话中发送私聊消息
     * @param $user
     * @param $chatId
     * @param $message
     * @return array
     * @throws AppException
     */
    public function sendMessageWhenChat($user, $chatId, $message)
    {
        $chatModel = new ChatModel();
        $chat = $chatModel->findById($chatId);

        // 聊天必须是正在通话中状态
        // 用户必须是参与通话一方
        if ($chat == null) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        if ($chat["status"] != ChatStatusEnum::CALLING) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        if ($user["id"] != $chat["s_u_id"] && $user["id"] != $chat["t_u_id"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        if ($user["id"] == $chat["t_u_id"]) {
            $tUId = $chat["s_u_id"];
        } else {
            $tUId = $chat["t_u_id"];
        }

        $messageArray = json_decode($message, true);
        $message["price"] = 0;
        $message["r_u_income"] = 0;
        $message = json_encode($messageArray);
        ShengWang::sendMessage($user["id"], $tUId, $message);

        return [
            "is_free" => 1,
            "price" => 0,
            "r_u_income" => 0,
        ];
    }

    /**
     * 获取声网RTM token
     * @param $user
     * @return array
     * @throws \Exception
     */
    public function getSWImToken($user)
    {
        $expire = 86400;
        $returnData = [
            "token" => ShengWang::getRtmToken($user["id"], $expire),
            "u_id" => $user["id"],
            "expire" => $expire,
        ];
        return $returnData;
    }

    public static function sendGiftImMessage($user, $rUId, $gift, $rUIncome)
    {
        $giftMessage = json_encode([
            "type" => ImMessageTypeEnum::GIFT,
            "gift_name" => $gift["name"],
            "gift_image_url" => $gift["image_url"],
            "price" => $gift["price"],
            "r_u_income" => $rUIncome,
        ]);
        ShengWang::sendMessage($user["id"], $rUId, $giftMessage);
    }
}