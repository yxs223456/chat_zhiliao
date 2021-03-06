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
use app\common\model\UserIncomeLogModel;
use app\common\model\UserSpendLogModel;
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
        $returnData = [
            "gift" => [],
        ];
        foreach ($gifts as $gift) {
            $returnData["gift"][] = [
                "id" => $gift["id"],
                "name" => $gift["name"],
                "image_url" => $gift["image_url"],
                "price" => $gift["price"],
            ];
        }
        return $returnData;
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
        // 不能送给自己
        if ($rUId == $user["id"]) {
            throw AppException::factory(AppException::GIFT_GIVE_SELF);
        }

        $redis = Redis::factory();

        // 判断接收方是否拉黑用户
        if (BlackListService::inUserBlackList($rUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 用户处于自己发起的聊天中，不允许调用该接口发送礼物
        if ($checkScene) {
            $chatModel = new ChatModel();
            $chat = $chatModel->where("t_u_id", $user["id"])
                ->where("status", ChatStatusEnum::CALLING)
                ->find();
            if ($chat) {
                throw AppException::factory(AppException::QUERY_API_ERROR);
            }
        }

        // 礼物详情，赠送者，接受者信息
        $gift = self::getGiftById($giftId, $redis);
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
        $rUserInfo = UserInfoService::getUserInfoById($rUId, $redis);

        // 收礼人礼物分润收入
        $bonusRate = Constant::GIFT_BONUS_RATE;
        $rUIncome = (int) round($bonusRate * $gift["price"]);
        // 礼物赠送数据库操作
        Db::startTrans();
        try {
            $this->giveDbOperate($userInfo, $gift, $rUserInfo, $gift["price"], $rUIncome, $bonusRate);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 向声网发送消息
        IMService::sendGiftImMessage($user, $rUId, $gift, $rUIncome);

        return [
            "gift_name" => $gift["name"],
            "gift_image_url" => $gift["image_url"],
            "gift_price" => $gift["price"],
            "r_u_income" => $rUIncome,
        ];
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


        $redis = Redis::factory();
        // 礼物详情
        $gift = self::getGiftById($giftId, $redis);

        if ($user["id"] == $chat["t_u_id"]) {
            // 接听方送礼物 只考虑礼物价格和自己的钱包余额即可
            $minBalance = $gift["price"];
            $giftRUId = $chat["s_u_id"];
        } else {
            // 拨打方送礼物不仅要考虑礼物价格和自己的钱包余额，还需考虑已经产生的聊天费用
            $chatPrice = ChatService::getCallingChatPay($chat); // 通话已经产生的费用
            $minBalance = $gift["price"] + $chatPrice;
            $giftRUId = $chat["t_u_id"];
        }

        // 赠送者，接受者信息
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
        $rUserInfo = UserInfoService::getUserInfoById($giftRUId, $redis);
        // 收礼人礼物分润收入
        $bonusRate = Constant::GIFT_BONUS_RATE;
        $rUIncome = (int) round($bonusRate * $gift["price"]);

        // 赠送礼物数据库操作
        Db::startTrans();
        try {

            $giveResp = $this->giveDbOperate($userInfo, $gift, $rUserInfo, $minBalance, $rUIncome, $bonusRate);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 向声网发送消息
        IMService::sendGiftImMessage($user, $giftRUId, $gift, $rUIncome);

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
     * 赠送礼物数据库
     * @param $userInfo     array 发送礼物用户
     * @param $gift         array/model   礼物
     * @param $rUserInfo    array   接受礼物用户
     * @param $minBalance   int   发送礼物用户最低账号余额，不足该余额时无法赠送礼物
     * @param $rUIncome     int   接受礼物用户可以收到的分润
     * @param $bonusRate    float 分润比例
     * @return array
     * @throws AppException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function giveDbOperate($userInfo, $gift, $rUserInfo, $minBalance, $rUIncome, $bonusRate)
    {
        // 判断用户账户余额是否足够。
        // 先花费不可提现聊币，再花费可提现聊币
        $userWallet = Db::name("user_wallet")->where("u_id", $userInfo["u_id"])->lock(true)->find();
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
        $rUserWallet = Db::name("user_wallet")->where("u_id", $rUserInfo["u_id"])->find();
        Db::name("user_wallet")
            ->where("id", $rUserWallet["id"])
            ->inc("income_amount", $rUIncome)
            ->inc("income_total_amount", $rUIncome)
            ->inc("total_balance", $rUIncome)
            ->update();

        // 礼物赠送纪录
        $giftGiveId = Db::name("gift_give_log")->insertGetId([
            "g_id" => $gift["id"],
            "s_u_id" => $userInfo["u_id"],
            "r_u_id" => $rUserInfo["u_id"],
            "give_time" => time(),
            "gift_amount" => 1,
            "gift_price" => $giftPrice,
            "r_u_income" => $rUIncome,
        ]);

        // 纪录送礼、收礼双方钱包流水
        $uLogMsg = (config("app.api_language")=="zh-tw")?
            "贈送 ".$rUserInfo["nickname"]." 禮物":
            "赠送 ".$rUserInfo["nickname"]." 礼物";
        $rULogMsg = (config("app.api_language")=="zh-tw")?
            "接收 ".$userInfo["nickname"]." 禮物":
            "接收 ".$userInfo["nickname"]." 礼物";
        Db::name("user_wallet_flow")->insertAll([
            [
                "u_id" => $userInfo["u_id"],
                "flow_type" => FlowTypeEnum::REDUCE,
                "amount" => $giftPrice,
                "add_type" => 0,
                "reduce_type" => WalletReduceEnum::GIFT,
                "object_source_id" => $giftGiveId,
                "before_balance" => $userWallet["total_balance"],
                "after_balance" => $userWallet["total_balance"] - $giftPrice,
                "create_date" => date("Y-m-d"),
                "log_msg" => $uLogMsg
            ],
            [
                "u_id" => $rUserInfo["u_id"],
                "flow_type" => FlowTypeEnum::ADD,
                "amount" => $rUIncome,
                "add_type" => WalletAddEnum::GIFT,
                "reduce_type" => 0,
                "object_source_id" => $giftGiveId,
                "before_balance" => $rUserWallet["total_balance"],
                "after_balance" => $rUserWallet["total_balance"] + $rUIncome,
                "create_date" => date("Y-m-d"),
                "log_msg" => $rULogMsg
            ]
        ]);

        // 添加支出纪录
        UserSpendLogModel::addLog(
            $userInfo["u_id"],
            $giftPrice,
            WalletReduceEnum::GIFT,
            $giftGiveId,
            $uLogMsg
        );

        // 添加收入纪录
        UserIncomeLogModel::addLog(
            $rUserInfo["u_id"],
            $rUIncome,
            WalletAddEnum::GIFT,
            $giftGiveId,
            $rULogMsg,
            $bonusRate
        );

        // 计算魅力值放入队列
        userGuardCallbackProduce($rUserInfo["u_id"], $userInfo["u_id"], $rUIncome, Redis::factory());

        return [
            "s_u_after_balance" => $userWallet["total_balance"] - $giftPrice,
            "t_u_after_balance" => $rUserWallet["total_balance"] + $rUIncome,
        ];
    }

    /**
     * 发红包
     * @param $user
     * @param $rUId
     * @param $amount
     * @param bool $checkScene
     * @return array
     * @throws AppException
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sendRedPackage($user, $rUId, $amount, $checkScene = true)
    {
        // 不能送给自己
        if ($rUId == $user["id"]) {
            throw AppException::factory(AppException::GIFT_GIVE_SELF);
        }

        $redis = Redis::factory();

        // 判断接收方是否拉黑用户
        if (BlackListService::inUserBlackList($rUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 用户处于自己发起的聊天中，不允许调用该接口发送礼物
        if ($checkScene) {
            $chatModel = new ChatModel();
            $chat = $chatModel->where("t_u_id", $user["id"])
                ->where("status", ChatStatusEnum::CALLING)
                ->find();
            if ($chat) {
                throw AppException::factory(AppException::QUERY_API_ERROR);
            }
        }
        // 赠送者，接受者信息
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
        $rUserInfo = UserInfoService::getUserInfoById($rUId, $redis);

        // 接收者分润收入
        $bonusRate = Constant::RED_PACKAGE_BONUS_RATE;
        $rUIncome = (int) round($bonusRate * $amount);
        // 礼物赠送数据库操作
        Db::startTrans();
        try {
            $this->giveRedPackageDbOperate($userInfo, $amount, $rUserInfo, $amount, $rUIncome, $bonusRate);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            "amount" => $amount,
            "r_u_income" => $rUIncome,
        ];
    }

    /**
     * 通话中发红包
     * @param $user
     * @param $chatId
     * @param $amount
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function sendRedPackageWhenChat($user, $chatId, $amount)
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
            // 接听方发红包 只考虑红包金额和自己的钱包余额即可
            $minBalance = $amount;
            $rpRUId = $chat["s_u_id"];
        } else {
            // 拨打方发红包不仅要考虑红包金额和自己的钱包余额，还需考虑已经产生的聊天费用
            $chatPrice = ChatService::getCallingChatPay($chat); // 通话已经产生的费用
            $minBalance = $amount + $chatPrice;
            $rpRUId = $chat["t_u_id"];
        }

        // 赠送者，接受者信息
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
        $rUserInfo = UserInfoService::getUserInfoById($rpRUId, $redis);

        // 接收者分润收入
        $bonusRate = Constant::RED_PACKAGE_BONUS_RATE;
        $rUIncome = (int) round($bonusRate * $amount);

        // 赠送礼物数据库操作
        Db::startTrans();
        try {
            $sendResp = $this->giveRedPackageDbOperate($userInfo, $amount, $rUserInfo, $minBalance, $rUIncome, $bonusRate);

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
                $balance = $sendResp["t_u_after_balance"];
            } else {
                $balance = $sendResp["s_u_after_balance"];
            }
            $payMinutes = floor($balance/$price);
            $minutes += $payMinutes;
        }

        return [
            "gift" => [
                "amount" => $amount,
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
     * 赠送红包数据库操作
     * @param $userInfo         array 发红包用户
     * @param $amount           int   红包金额
     * @param $rUserInfo        array 收红包物用户
     * @param $minBalance       int   发红包用户最低账号余额，不足该余额时无法发红包
     * @param $rUIncome         int   接受礼物用户可以收到的分润
     * @param $bonusRate        float 分润比例
     * @return array
     * @throws AppException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function giveRedPackageDbOperate($userInfo, $amount, $rUserInfo, $minBalance, $rUIncome, $bonusRate)
    {
        // 判断用户账户余额是否足够。
        // 先花费不可提现聊币，再花费可提现聊币
        $userWallet = Db::name("user_wallet")->where("u_id", $userInfo["u_id"])->lock(true)->find();
        $balanceAmount = $userWallet["balance_amount"]; // 用户充值余额
        $incomeAmount = $userWallet["income_amount"]; // 用户收入余额

        if ($balanceAmount + $incomeAmount < $minBalance) {
            throw AppException::factory(AppException::WALLET_MONEY_LESS);
        } else if ($balanceAmount < $amount) {
            $incomeAmountFree = $amount - $balanceAmount;
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $balanceAmount)
                ->dec("income_amount", $incomeAmountFree)
                ->dec("total_balance", $amount)
                ->update();
        } else {
            Db::name("user_wallet")
                ->where("id", $userWallet["id"])
                ->dec("balance_amount", $amount)
                ->dec("total_balance", $amount)
                ->update();
        }

        // 增加收礼人钱包余额
        $rUserWallet = Db::name("user_wallet")->where("u_id", $rUserInfo["u_id"])->find();
        Db::name("user_wallet")
            ->where("id", $rUserWallet["id"])
            ->inc("income_amount", $rUIncome)
            ->inc("income_total_amount", $rUIncome)
            ->inc("total_balance", $rUIncome)
            ->update();

        // 红包赠送纪录
        $rPGiveId = Db::name("gift_rp_give_log")->insertGetId([
            "s_u_id" => $userInfo["u_id"],
            "r_u_id" => $rUserInfo["u_id"],
            "give_time" => time(),
            "amount" => $amount,
            "r_u_income" => $rUIncome,
        ]);

        // 纪录送礼、收礼双方钱包流水
        $uLogMsg = (config("app.api_language")=="zh-tw")?
            "贈送 ".$rUserInfo["nickname"]." 紅包":
            "赠送 ".$rUserInfo["nickname"]." 红包";
        $rULogMsg = (config("app.api_language")=="zh-tw")?
            "接收 ".$userInfo["nickname"]." 紅包":
            "接收 ".$userInfo["nickname"]." 红包";
        Db::name("user_wallet_flow")->insertAll([
            [
                "u_id" => $userInfo["u_id"],
                "flow_type" => FlowTypeEnum::REDUCE,
                "amount" => $amount,
                "add_type" => 0,
                "reduce_type" => WalletReduceEnum::RED_PACKAGE,
                "object_source_id" => $rPGiveId,
                "before_balance" => $userWallet["total_balance"],
                "after_balance" => $userWallet["total_balance"] - $amount,
                "create_date" => date("Y-m-d"),
                "log_msg" => $uLogMsg,
            ],
            [
                "u_id" => $rUserInfo["u_id"],
                "flow_type" => FlowTypeEnum::ADD,
                "amount" => $rUIncome,
                "add_type" => WalletAddEnum::RED_PACKAGE,
                "reduce_type" => 0,
                "object_source_id" => $rPGiveId,
                "before_balance" => $rUserWallet["total_balance"],
                "after_balance" => $rUserWallet["total_balance"] + $rUIncome,
                "create_date" => date("Y-m-d"),
                "log_msg" => $rULogMsg,
            ]
        ]);

        // 添加支出纪录
        UserSpendLogModel::addLog(
            $userInfo["u_id"],
            $amount,
            WalletReduceEnum::RED_PACKAGE,
            $rPGiveId,
            $uLogMsg
        );

        // 添加收入纪录
        UserIncomeLogModel::addLog(
            $rUserInfo["u_id"],
            $rUIncome,
            WalletAddEnum::RED_PACKAGE,
            $rPGiveId,
            $rULogMsg,
            $bonusRate
        );

        // 计算魅力值放入队列
        userGuardCallbackProduce($rUserInfo["u_id"], $userInfo["u_id"], $rUIncome, Redis::factory());

        return [
            "s_u_after_balance" => $userWallet["total_balance"] - $amount,
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