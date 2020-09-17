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
use app\common\helper\Redis;
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
                $tUser = UserService::getUserById($tUId, $redis);
                if ($tUser["sex"] != UserSexEnum::FEMALE) {
                    break;
                }
                $tUInfo = UserInfoService::getUserInfoById($tUId, $redis);
                if ($tUInfo["is_pretty"] == UserIsPrettyEnum::NO) {
                    break;
                }
                // 接听方今日免费接听分钟数
                $tUFreeMinutes = self::getFreeMinutes($tUId, $redis);
                // 本次通话可以使用的免费分钟数
                $freeMinutes = $freeChatWallet["free_minutes"] <= $tUFreeMinutes ?
                    $freeChatWallet["free_minutes"] :
                    $tUFreeMinutes;
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
            $whereInStr = "status in (".ChatStatusEnum::WAIT_ANSWER.",".ChatStatusEnum::CALLING.") ";
            $oldChat = Db::name("chat")
                ->where("(s_u_id=$tUId and $whereInStr) or " .
                    "(t_u_id=$tUId and $whereInStr)")
                ->lock(true)
                ->find();
            if ($oldChat) {
                throw AppException::factory(AppException::CHAT_LINE_BUSY);
            }

            $oldChat = Db::name("chat")
                ->where("(s_u_id={$user['id']} and $whereInStr) or " .
                    "(t_u_id={$user['id']} and $whereInStr)")
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
                "status" => ChatStatusEnum::WAIT_ANSWER,
            ];
            $chatId = Db::name("chat")->insertGetId($chatData);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        $returnData = [
            "chat_id" => $chatId,
        ];

        return $returnData;
    }

    /**
     * 计算用户免费接听时长
     * @param $userId
     * @param null $redis
     * @return int
     */
    public static function getFreeMinutes($userId, $redis = null) :int
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
            return 3;
        } else {
            return $freeMinutesInfo["free_minutes"];
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
            if ($user["id"] != $chat["s_u_id "] && $user["id"] != $chat["t_u_id"]) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($chat["status"] == ChatStatusEnum::CALLING) {
                throw AppException::factory(AppException::CHAT_HANG_UP_CALLING);
            }
            if ($chat["status"] != ChatStatusEnum::WAIT_ANSWER) {
                throw AppException::factory(AppException::CHAT_NOT_WAIT_ANSWER);
            }

            $chatUpdateData = [
                "status" => ChatStatusEnum::NO_ANSWER,
                "hang_up_id" => $user["id"]
            ];
            Db::name("chat")->where("id", $chatId)->update($chatUpdateData);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return new \stdClass();
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
        // 只有通话状态处于待接听时才可挂断通话请求
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
            $minutes = $chat["free_minutes"];   // 不免费时通话时长
            if (!$isFree) {
                $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->find();
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

        $returnData = [
            "is_free" => $isFree,
            "current_time" => time(),
            "deadline" => $isFree ? 0 : time() + ($minutes * 60),
        ];
        return $returnData;
    }

    public function end($user, $chatId)
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
            if ($user["id"] != $chat["s_u_id "] && $user["id"] != $chat["t_u_id"]) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($chat["status"] != ChatStatusEnum::CALLING) {
                throw AppException::factory(AppException::CHAT_NOT_CALLING);
            }



            // 后续处理通过队列异步处理
            // 数据库纪录回调数据
            $callbackData = [
                "chat_id" => $chatId,
                "hang_up_id" => $user["id"],
                "hang_up_time" => time(),
            ];
            Db::name("tmp_chat_end_callback")->insert($callbackData);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return new \stdClass();
    }
}