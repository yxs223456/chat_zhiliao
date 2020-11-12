<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:35
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\ChatStatusEnum;
use app\common\enum\ChatTypeEnum;
use app\common\enum\UserIsPrettyEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\enum\WalletReduceEnum;
use app\common\helper\Redis;
use app\common\helper\ShengWang;
use app\common\model\UserWalletFlowModel;
use app\gateway\GatewayClient;
use think\facade\Db;

class ChatService extends Base
{
    /**
     * 初始化通话
     * @param $user
     * @param $tUId
     * @param $chatType
     * @return array
     * @throws AppException
     * @throws \Throwable
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function dial($user, $tUId, $chatType)
    {
        // 不能和自己通话
        if ($tUId == $user["id"]) {
            throw AppException::factory(AppException::CHAT_NOT_SELF);
        }

        // 接听方个人设置
        $redis = Redis::factory();
        $tUSet = UserSetService::getUserSetByUId($tUId, $redis);
        if (empty($tUSet)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 判断接听方是否关闭接听
        if ($chatType == ChatTypeEnum::VIDEO && $tUSet["video_chat_switch"] == UserSwitchEnum::OFF) {
            throw AppException::factory(AppException::CHAT_VIDEO_CLOSE);
        } elseif ($chatType == ChatTypeEnum::VOICE && $tUSet["voice_chat_switch"] == UserSwitchEnum::OFF) {
            throw AppException::factory(AppException::CHAT_VOICE_CLOSE);
        }

        // 判断接听方是否拉黑用户
        if (BlackListService::inUserBlackList($tUId, $user["id"], $redis)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 接听方设置的接听价格
        $price = ChatTypeEnum::VIDEO ? $tUSet["video_chat_price"] : $tUSet["voice_chat_price"];
        $isFree = $price == 0 ? 1 : 0;  // 通话是否免费
        $freeMinutes = 0;               // 可以免费通话分钟数
        $freeMinutesIsFree = 0;         // 免费通话分钟数是否真正免费

        // 不免费情况下计算免费通话分钟数和付费通话时长
        if (!$isFree) {
            // 免费通话分钟数计算
            do {
                // 用户如果没有免费通话分钟数，不用后续计算
                $freeChatWallet = Db::name("chat_free_wallet")->where("u_id", $user["id"])->find();
                if ($freeChatWallet["free_minutes"] == 0) {
                    break;
                }
                // (只有女神有免费接听分钟数)如果接听方是女神，计算接听方今日剩余免费接听分钟数。
                /*$tUser = UserService::getUserById($tUId, $redis);
                if ($tUser["sex"] != UserSexEnum::FEMALE) {
                    break;
                }*/
                /*$tUInfo = UserInfoService::getUserInfoById($tUId, $redis);
                if ($tUInfo["is_pretty"] == UserIsPrettyEnum::NO) {
                    break;
                }*/
                // 接听方今日免费接听分钟数
                $tUFreeMinutesInfo = self::getFreeMinutes($tUId, $redis);
                // 本次通话可以使用的免费分钟数
                $freeMinutes = $freeChatWallet["free_minutes"] <= $tUFreeMinutesInfo["minutes"] ?
                    $freeChatWallet["free_minutes"] :
                    $tUFreeMinutesInfo["minutes"];
                $freeMinutesIsFree = $tUFreeMinutesInfo["is_free"];
            } while(0);

            // 通话不免费时，总通话时长为0无法发起聊天
            if ($freeMinutes == 0) {
                // 计算付费通话时长
                $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->find();
                $payMinutes = floor($userWallet["total_balance"]/$price);
                if ($payMinutes == 0) {
                    throw AppException::factory(AppException::CHAT_LESS_MONTY);
                }
            }
        }

        Db::startTrans();
        try {
            // 双方有人正在通话中，无法发起新通话
            $whereStatusStr = "status in (".ChatStatusEnum::WAIT_ANSWER.",".ChatStatusEnum::CALLING.") ";
            $oldChat = Db::name("chat")
                ->where("(s_u_id=$tUId and $whereStatusStr) or " .
                    "(t_u_id=$tUId and $whereStatusStr)")
                ->lock(true)
                ->find();
            if ($oldChat) {
                throw AppException::factory(AppException::CHAT_LINE_BUSY);
            }

            $oldChat = Db::name("chat")
                ->where("(s_u_id={$user['id']} and $whereStatusStr) or " .
                    "(t_u_id={$user['id']} and $whereStatusStr)")
                ->lock(true)
                ->find();
            if ($oldChat) {
                throw AppException::factory(AppException::CHAT_USER_CHAT_ING);
            }

            // 数据库纪录通话纪录
            $chatData = [
                "s_u_id" => $user["id"],
                "t_u_id" => $tUId,
                "chat_type" => $chatType,
                "s_user_price" => 0,
                "t_user_price" => $price,
                "free_minutes" => $freeMinutes,
                "free_minutes_is_free" => $freeMinutesIsFree,
                "status" => ChatStatusEnum::WAIT_ANSWER,
            ];
            $chatId = Db::name("chat")->insertGetId($chatData);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 声网token
        $swTokenInfo = $this->getSwRtcToken($user, $chatId);

        $returnData = [
            "chat_id" => $chatId,
            "sw_token_info" => $swTokenInfo,
        ];

        return $returnData;
    }

    /**
     * 计算用户免费接听时长
     * @param $userId
     * @param null $redis
     * @return array
     */
    public static function getFreeMinutes($userId, $redis = null)
    {
        // 女神免费接听分钟数策略:
        // 1、平台上的女神，每天都有3分钟的免费接听时长。
        // 2、拨打方需有免费拨打时长，才可消耗女神的免费接听时长。
        // 3、若拨打方无免费拨打时长，或该女神当天的免费接听时长已消耗完，则该路通话恢复计费。
        // 4、当您的免费时长消耗完毕后，系统会再次分配给您两分钟的免费时长，同时会将这两分钟的分成直接冲入您的余额，
        //    您不用担心会一直免费接视频，除最开始的三分钟外，后续的免费视频都是由系统提前给您补贴。
        if ($redis == null) {
            $redis = Redis::factory();
        }

        $today = date("Y-m-d");
        $freeMinutesInfo = getUserChatFreeMinutes($userId, $today, $redis);
        if (empty($freeMinutesInfo)) {
            return [
                "minutes" => 3,
                "is_free" => 1,
            ];
        } else {
            return [
                "minutes" => 2,
                "is_free" => 0,
            ];
        }
    }
    /**
     * 挂断通话请求
     * @param $user
     * @param $chatId
     * @return \stdClass
     * @throws \Throwable
     */
    public function hangUp($user, $chatId)
    {
        // 只有通话状态处于待接听时才可挂断通话请求
        // 只有通话双方可以挂断通话
        // 因为没有通话，所以无需其他处理
        Db::startTrans();
        try {
            $chat = Db::name("chat")->where("id", $chatId)->lock(true)->find();
            if (empty($chat)) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($user["id"] != $chat["s_u_id"] && $user["id"] != $chat["t_u_id"]) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($chat["status"] == ChatStatusEnum::CALLING) {
                throw AppException::factory(AppException::CHAT_HANG_UP_CALLING);
            }
            if ($chat["status"] == ChatStatusEnum::WAIT_ANSWER) {
                $chatUpdateData = [
                    "status" => ChatStatusEnum::NO_ANSWER,
                    "hang_up_id" => $user["id"]
                ];
                Db::name("chat")->where("id", $chatId)->update($chatUpdateData);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return new \stdClass();
    }

    /**
     * 取声网 1v1通话 RTC token
     * @param $user
     * @param $chatId
     * @return array
     * @throws \Exception
     */
    public function getSwRtcToken($user, $chatId)
    {
        $expire = 86400;
        $token = ShengWang::getRtcToken($user["id"], $chatId, $expire);

        $returnData = [
            "token" => $token,
            "u_id" => $user["id"],
            "channel_name" => $chatId,
            "expire" => $expire,
        ];
        return $returnData;
    }

    /**
     * 接听通话请求
     * @param $user
     * @param $chatId
     * @return array
     * @throws \Throwable
     */
    public function answer($user, $chatId)
    {
        // 只有通话状态处于待接听时才可接听通话请求
        // 只有通话接听方可以接听
        Db::startTrans();
        try {
            $chat = Db::name("chat")->where("id", $chatId)->lock(true)->find();
            if (empty($chat)) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($user["id"] != $chat["t_u_id"]) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($chat["status"] != ChatStatusEnum::WAIT_ANSWER) {
                throw AppException::factory(AppException::CHAT_NOT_WAIT_ANSWER);
            }

            // 计算允许通话时长
            $price = $chat["t_user_price"];     // 通话价格
            $isFree = $price == 0 ? 1 : 0;      // 通话是否免费
            $minutes = $chat["free_minutes"];   // 不免费时最大通话分钟数
            if (!$isFree) {
                $userWallet = Db::name("user_wallet")->where("u_id", $chat["s_u_id"])->find();
                $payMinutes = floor($userWallet["total_balance"]/$price);
                $minutes += $payMinutes;
                if ($minutes == 0) {
                    throw AppException::factory(AppException::CHAT_LESS_MONTY);
                }
            }

            $chatUpdateData = [
                "status" => ChatStatusEnum::CALLING,
                "chat_begin_time" => time()
            ];
            Db::name("chat")->where("id", $chatId)->update($chatUpdateData);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 声网token
        $swTokenInfo = $this->getSwRtcToken($user, $chatId);

        $returnData = [
            "is_free" => $isFree,
            "current_time" => time(),
            "deadline" => $isFree ? 0 : time() + ($minutes * 60),
            "sw_token_info" => $swTokenInfo,
        ];
        return $returnData;
    }

    /**
     * 结束通话
     * @param $userId
     * @param $chatId
     * @return \stdClass
     * @throws \Throwable
     */
    public function end($userId, $chatId)
    {
        // 只有通话状态处于待接听时才可挂断通话请求
        // 只有通话双方可以挂断通话
        // 需要后续处理
        Db::startTrans();
        try {
            $chat = Db::name("chat")->where("id", $chatId)->lock(true)->find();
            if (empty($chat)) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($userId != $chat["s_u_id"] && $userId != $chat["t_u_id"]) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($chat["status"] == ChatStatusEnum::CALLING) {
                // 修改通话纪录
                $chatUpdateData = [
                    "status" => ChatStatusEnum::END,
                    "chat_end_time" => time(),
                    "chat_time_length" => time() - $chat["chat_begin_time"],
                    "hang_up_id" => $userId,
                ];
                Db::name("chat")->where("id", $chatId)->update($chatUpdateData);

                // 计算本次聊天费用
                $chat["chat_end_time"] = $chatUpdateData["chat_end_time"];
                $chat["chat_time_length"] = $chatUpdateData["chat_time_length"];
                $price = self::getEndChatPay($chat);
                if ($price > 0) {
                    // 扣除拨打人钱包余额
                    $sUWallet = Db::name("user_wallet")
                        ->where("u_id", $chat["s_u_id"])
                        ->lock(true)
                        ->find();
                    $sUWalletUpdate = Db::name("user_wallet")
                        ->where("id", $sUWallet["id"]);
                    if ($sUWallet["balance_amount"] >= $price) {
                        $sUWalletUpdate->dec("balance_amount", $price)
                            ->dec("total_balance", $price)
                            ->update();
                    } else if ($sUWallet["total_balance"] >= $price) {
                        $sUWalletUpdate->dec("balance_amount", $sUWallet["balance_amount"])
                            ->dec("income_amount", $price - $sUWallet["balance_amount"])
                            ->dec("total_balance", $price)
                            ->update();
                    } else {
                        $sUWalletUpdate->update([
                            "balance_amount" => 0,
                            "income_amount" => 0,
                            "total_balance" => 0,
                        ]);
                        $price = $sUWallet["total_balance"];
                    }

                    // 纪录拨打人钱包流水
                    UserWalletFlowModel::reduceFlow(
                        $chat["s_u_id"],
                        $price,
                        $chat["chat_type"] == ChatTypeEnum::VIDEO ?
                            WalletReduceEnum::VIDEO_CHAT : WalletReduceEnum::VOICE_CHAT,
                        $chatId,
                        $sUWallet["total_balance"],
                        $sUWallet["total_balance"] - $price
                    );
                }

                // 后续处理通过队列异步处理
                // 数据库纪录回调数据
                $callbackData = [
                    "chat_id" => $chatId,
                    "s_u_pay" => $price,
                ];
                Db::name("tmp_chat_end_callback")->insert($callbackData);
            }

            Db::commit();

            // 把后续处理任务放入队列
            chatEndCallbackProduce($chatId, Redis::factory());

            // 更新接听人免费接听数
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return new \stdClass();
    }

    /**
     * 聊天纪录
     * @param $user
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function chatList($user, $pageNum, $pageSize)
    {
        $returnData["list"] = [];
        $list = Db::name("chat")
            ->where("s_u_id=".$user["id"]." or t_u_id=".$user["id"])
            ->order("id", "desc")
            ->limit(($pageNum-1)*$pageSize, $pageSize)
            ->select();

        if (empty($list)) {
            return $returnData;
        }

        $userIds = [];
        foreach ($list as $item) {
            if ($item["s_u_id"] == $user["id"]) {
                $userIds[] = $item["t_u_id"];
            } elseif ($item["t_u_id"] == $user["id"]) {
                $userIds[] = $item["s_u_id"];
            }
        }

        $usersInfo = Db::name("user_info")
            ->whereIn("u_id", $userIds)
            ->field("u_id,portrait,nickname")
            ->select()
            ->toArray();
        $usersInfo = array_column($usersInfo, null, "u_id");

        foreach ($list as $item) {
            if ($user["id"] == $item["s_u_id"]) {
                $uId = $item["t_u_id"];
                $callType = 1;
            } else {
                $uId = $item["s_u_id"];
                $callType = 2;
            }

            $isOnline = GatewayClient::isUidOnline($uId);
            $returnData["list"][] = [
                "u_id" => $uId,
                "nickname" =>  $usersInfo[$uId]["nickname"],
                "portrait" =>  $usersInfo[$uId]["portrait"],
                "chat_type" => $item["chat_type"],
                "call_type" => $callType,
                "chat_status_message" => $this->chatStatusMessage($item),
                "chat_create_time" => date("m-d H:i", strtotime($item["create_time"])),
                "is_online" => $isOnline,
            ];
        }

        return $returnData;
    }

    private function chatStatusMessage($chat, $lang = null)
    {
        if ($lang == null) {
            $lang = config("app.api_language");
        }

        if ($chat["status"] == ChatStatusEnum::NO_ANSWER) {
            $message = ($lang == "zh-cn")?"通话已取消":"通話已取消";
        } elseif ($chat["status"] == ChatStatusEnum::WAIT_ANSWER) {
            $message = ($lang == "zh-cn")?"拨打中":"撥打中";
        } elseif ($chat["status"] == ChatStatusEnum::CALLING) {
            $message = ($lang == "zh-cn")?"通话中":"通話中";
        } else {
            $mLength = (int)($chat["chat_time_length"]/60);
            $sLength = $chat["chat_time_length"]%60;
            $sLength<10 ? $sLength="0".$sLength : null;
            $timeLength = $mLength.":".$sLength;
            $message = ($lang == "zh-cn")?"通话时长 ".$timeLength:"通話時長 ".$timeLength;
        }
        return $message;
    }


    /**
     * 计算已结束通话费用
     * @param $chat
     * @return int
     */
    public static function getEndChatPay($chat) : int
    {
        $minutes = self::getChatMinutes($chat);
        $needPayMinutes = $minutes - $chat["free_minutes"];
        $needPayMinutes = $needPayMinutes <= 0 ? 0 : $needPayMinutes;
        return $chat["t_user_price"] * $needPayMinutes;
    }

    /**
     * 计算通话分钟数，不够一分钟时 >=3秒 按一分钟计算
     * @param $chat
     * @return int
     */
    public static function getChatMinutes($chat) : int
    {
        $timeLength = $chat["chat_time_length"];
        $minutes = floor($timeLength/60);
        if ($minutes == 0) {
            return 1;
        }

        $seconds = $timeLength % 60;
        if ($seconds >= 3) {
            return $minutes + 1;
        } else {
            return $minutes;
        }
    }

    /**
     * 计算通话中通话已经产生的费用
     * @param $chat
     * @return int
     */
    public static function getCallingChatPay($chat) : int
    {
        $minutes = (int) ceil((time() - $chat["chat_begin_time"])/60);
        $needPayMinutes = $minutes - $chat["free_minutes"];
        $needPayMinutes = $needPayMinutes <= 0 ? 0 : $needPayMinutes;
        return $chat["t_user_price"] * $needPayMinutes;
    }
}