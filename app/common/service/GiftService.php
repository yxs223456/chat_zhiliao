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

    public function giveWhenChat($user, $chatId, $gift_id)
    {

    }

    /**
     * 无特殊场景下（通话中、私聊）赠送礼物
     * @param $user
     * @param $rUId
     * @param $giftId
     * @return array
     * @throws AppException
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function give($user, $rUId, $giftId)
    {
        $redis = Redis::factory();

        // 礼物详情
        $gift = self::getGiftById($giftId, $redis);

        // 礼物接收用户
        $receiveUser = UserService::getUserById($rUId, $redis);
        if (empty($receiveUser)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 判断接收方是否拉黑用户
        if (BlackListService::inUserBlackList($rUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 用户处于聊天中，不允许调用该接口发送礼物
        $chatModel = new ChatModel();
        $chat = $chatModel->where("t_u_id", $user["id"])
            ->where("status", ChatStatusEnum::CALLING)
            ->find();
        if ($chat) {
            throw AppException::factory(AppException::QUERY_API_ERROR);
        }

        // 礼物赠送过程
        $rUIncome = floor(Constant::GIFT_BONUS_RATE * $gift["price"]); // 收礼人礼物分润收入
        Db::startTrans();
        try {
            $this->giveDbOperate($user, $gift, $receiveUser, $rUIncome);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            "name" => $gift["name"],
            "image_url" => $gift["image_url"],
            "price" => $gift["price"],
            "r_u_income" => $rUIncome,
        ];
    }

    private function giveDbOperate($user, $gift, $receiveUser, $rUIncome)
    {
        // 判断用户账户余额是否足够。
        // 先花费不可提现聊币，再花费可提现聊币
        $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->lock(true)->find();
        $balanceAmount = $userWallet["balance_amount"]; // 用户充值余额
        $incomeAmount = $userWallet["income_amount"]; // 用户收入余额
        $price = $gift["price"]; // 礼物价格
        if ($balanceAmount + $incomeAmount < $price) {
            throw AppException::factory(AppException::WALLET_MONEY_LESS);
        } else if ($balanceAmount < $price) {
            $incomeAmountFree = $price - $balanceAmount;
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $balanceAmount)
                ->dec("income_amount", $incomeAmountFree)
                ->dec("total_balance", $price)
                ->update();
        } else {
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $price)
                ->dec("total_balance", $price)
                ->update();
        }

        // 增加收礼人钱包余额
        $rUserWallet = Db::name("user_wallet")->where("u_id", $receiveUser["id"])->find();
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
            "r_u_id" => $receiveUser["id"],
            "give_time" => time(),
            "gift_amount" => 1,
            "gift_price" => $price,
            "r_u_income" => $rUIncome,
        ]);

        // 纪录送礼、收礼双方钱包流水
        Db::name("user_wallet_flow")->insertAll([
            [
                "u_id" => $user["id"],
                "flow_type" => FlowTypeEnum::REDUCE,
                "amount" => $price,
                "add_type" => WalletReduceEnum::GIFT,
                "object_source_id" => $giftGiveId,
                "before_balance" => $userWallet["total_balance"],
                "after_balance" => $userWallet["total_balance"] - $price,
                "create_date" => date("Y-m-d"),
            ],
            [
                "u_id" => $user["id"],
                "flow_type" => FlowTypeEnum::ADD,
                "amount" => $rUIncome,
                "add_type" => WalletAddEnum::GIFT,
                "object_source_id" => $giftGiveId,
                "before_balance" => $rUserWallet["total_balance"],
                "after_balance" => $rUserWallet["total_balance"] + $rUIncome,
                "create_date" => date("Y-m-d"),
            ]
        ]);
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