<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-07
 * Time: 11:15
 */

namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\ChatStatusEnum;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\FlowTypeEnum;
use app\common\enum\GiftIsSaleEnum;
use app\common\enum\WalletAddEnum;
use app\common\enum\WalletReduceEnum;
use app\common\helper\Redis;
use app\common\model\ChatModel;
use app\common\model\ConfigGiftModel;
use think\facade\Db;

class GiftService extends Base
{
    /**
     * 所有上架的礼物
     * @return array
     */
    public function getAll()
    {
        // 首先从缓存读取数据
        $redis = Redis::factory();
        $gifts = getAllSaleGift($redis);

        // 缓存失效从数据库获取数据，并重新生成缓存
        if (empty($gifts)) {
            $giftConfigModel = new ConfigGiftModel();
            $gifts = $giftConfigModel->getAll();
            cacheAllSaleGift($gifts, $redis);
        }

        // 格式化返回数据
        $returnData = [];
        foreach ($gifts as $gift) {
            $returnData[] = [
                "id" => $gift["id"],
                "name" => $gift["name"],
                "image_url" => $gift["image_url"],
                "price" => $gift["price"],
            ];
        }
        return $returnData;
    }

    /**
     * 通话中赠送礼物
     * @param $user
     * @param $chatId
     * @param $giftId
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function giveWhenChat($user, $chatId, $giftId)
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

        // 礼物详情
        $gift = self::getGiftById($giftId, Redis::factory());
        // 收礼人礼物分润收入
        $rUIncome = floor(Constant::GIFT_BONUS_RATE * $gift["price"]);

        // 赠送礼物数据库操作
        Db::startTrans();
        try {
            if ($user["id"] == $chat["t_u_id"]) {
                // 接听方送礼物 只考虑礼物价格和自己的钱包余额即可
                $minBalance = $gift["price"];
            } else {
                // 拨打方送礼物不仅要考虑礼物价格和自己的钱包余额，还需考虑已经产生的聊天费用
                $chatPrice = ChatService::getCallingChatPay($chat); // 通话已经产生的费用
                $minBalance = $gift["price"] + $chatPrice;
            }
            $giveResp = $this->giveDbOperate($user, $gift, $chat["t_u_id"], $minBalance, $rUIncome);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 再次计算通话时长
        $price = $chat["t_user_price"];     // 通话价格
        $isFree = $price == 0 ? 1 : 0;      // 通话是否免费
        $minutes = $chat["free_minutes"];   // 不免费时最大通话分钟数
        if (!$isFree) {
            // 拨打方剩余余额
            if ($user["id"] == $chat["t_u_id"]) {
                $balance = $giveResp["t_u_after_balance"];
            } else {
                $balance = $giveResp["s_u_after_balance"];
            }
            $payMinutes = floor($balance/$price);
            $minutes += $payMinutes;
        }

        return [
            "gift" => [
                "gift_name" => $gift["name"],
                "gift_image_url" => $gift["image_url"],
                "gift_price" => $gift["price"],
                "r_u_income" => $rUIncome,
            ],
            "chat" => [
                "is_free" => $isFree,
                "current_time" => time(),
                "deadline" => $isFree ? 0 : $chat["chat_begin_time"] + ($minutes * 60),
            ],
        ];
    }

    /**
     * 不需考虑场景（通话中等）赠送礼物
     * @param $user
     * @param $rUId
     * @param $giftId
     * @param bool $checkScene
     * @return array
     * @throws AppException
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function give($user, $rUId, $giftId, $checkScene = true)
    {
        $redis = Redis::factory();

        // 礼物详情
        $gift = self::getGiftById($giftId, $redis);

        // 判断接收方是否拉黑用户
        if (BlackListService::inUserBlackList($rUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 用户处于聊天中，不允许调用该接口发送礼物
        if ($checkScene) {
            $chatModel = new ChatModel();
            $chat = $chatModel->where("t_u_id", $user["id"])
                ->where("status", ChatStatusEnum::CALLING)
                ->find();
            if ($chat) {
                throw AppException::factory(AppException::QUERY_API_ERROR);
            }
        }

        // 收礼人礼物分润收入
        $rUIncome = floor(Constant::GIFT_BONUS_RATE * $gift["price"]);
        // 礼物赠送数据库操作
        Db::startTrans();
        try {
            $this->giveDbOperate($user, $gift, $rUId, $gift["price"], $rUIncome);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            "gift_name" => $gift["name"],
            "gift_image_url" => $gift["image_url"],
            "gift_price" => $gift["price"],
            "r_u_income" => $rUIncome,
        ];
    }

    /**
     * 发送礼物
     * @param $user         array/model 发送礼物用户
     * @param $gift         array/model   礼物
     * @param $rUId         int   接受礼物用户id
     * @param $minBalance   int   发送礼物用户最下账号余额，不足该余额时无法赠送礼物
     * @param $rUIncome     int   接受礼物用户可以收到的分润
     * @return array
     * @throws AppException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function giveDbOperate($user, $gift, $rUId, $minBalance, $rUIncome)
    {
        // 判断用户账户余额是否足够。需要考虑聊天花费
        // 先花费不可提现聊币，再花费可提现聊币
        $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->lock(true)->find();
        $balanceAmount = $userWallet["balance_amount"]; // 用户充值余额
        $incomeAmount = $userWallet["income_amount"]; // 用户收入余额
        $giftPrice = $gift["price"]; // 礼物价格

        if ($balanceAmount + $incomeAmount < $minBalance) {
            throw AppException::factory(AppException::WALLET_MONEY_LESS);
        } else if ($balanceAmount < $giftPrice) {
            $incomeAmountFree = $giftPrice - $balanceAmount;
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $balanceAmount)
                ->dec("income_amount", $incomeAmountFree)
                ->dec("total_balance", $giftPrice)
                ->update();
        } else {
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $giftPrice)
                ->dec("total_balance", $giftPrice)
                ->update();
        }

        // 增加收礼人钱包余额
        $rUserWallet = Db::name("user_wallet")->where("u_id", $rUId)->find();
        Db::name("user_wallet")
            ->where("id", $rUserWallet["id"])
            ->inc("income_amount", $rUIncome)
            ->inc("income_total_amount", $rUIncome)
            ->inc("total_balance", $rUIncome)
            ->update();

        // 礼物赠送纪录
        $giftGiveId = Db::name("gift_give_log")->insertGetId([
            "g_id" => $gift["id"],
            "s_u_id" => $user["id"],
            "r_u_id" => $rUId,
            "give_time" => time(),
            "gift_amount" => 1,
            "gift_price" => $giftPrice,
            "r_u_income" => $rUIncome,
        ]);

        // 纪录送礼、收礼双方钱包流水
        Db::name("user_wallet_flow")->insertAll([
            [
                "u_id" => $user["id"],
                "flow_type" => FlowTypeEnum::REDUCE,
                "amount" => $giftPrice,
                "add_type" => WalletReduceEnum::GIFT,
                "object_source_id" => $giftGiveId,
                "before_balance" => $userWallet["total_balance"],
                "after_balance" => $userWallet["total_balance"] - $giftPrice,
                "create_date" => date("Y-m-d"),
            ],
            [
                "u_id" => $rUId,
                "flow_type" => FlowTypeEnum::ADD,
                "amount" => $rUIncome,
                "add_type" => WalletAddEnum::GIFT,
                "object_source_id" => $giftGiveId,
                "before_balance" => $rUserWallet["total_balance"],
                "after_balance" => $rUserWallet["total_balance"] + $rUIncome,
                "create_date" => date("Y-m-d"),
            ]
        ]);

        return [
            "s_u_after_balance" => $userWallet["total_balance"] - $giftPrice,
            "t_u_after_balance" => $rUserWallet["total_balance"] + $rUIncome,
        ];
    }

    public static function getGiftById($giftId, $redis)
    {
        $gift = getGiftByIdOnRedis($giftId, $redis);
        if (empty($gift)) {
            $giftConfigModel = new ConfigGiftModel();
            $gift = $giftConfigModel->findById($giftId);
            if (empty($gift)) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($gift["is_sale"] == GiftIsSaleEnum::NO ||
                $gift["is_delete"] == DbDataIsDeleteEnum::YES)
            {
                throw AppException::factory(AppException::GIFT_OFFLINE);
            }
            $gift = $gift->toArray();
        }
        return $gift;
    }
}