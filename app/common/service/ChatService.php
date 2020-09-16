<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:35
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\ChatTypeEnum;
use app\common\enum\UserIsPrettyEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\helper\Redis;
use think\facade\Db;

class ChatService extends Base
{
    public function init($user, $tUId, $chatType)
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

        // 初始化返回数据
        $isFree = 1;       // 通话是否免费
        $freeMinutes = 0;  // 可以免费通话分钟数
        $totalMinutes = 0; // 可以总通话分钟数

        // 不免费情况下计算免费通话分钟数和付费通话时长
        if ($price != 0) {
            $isFree = 0;
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

            // 计算付费通话时长
            $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->find();
            $payMinutes = floor($userWallet["total_balance"]/$price);
            $totalMinutes = $freeMinutes + $payMinutes;
        }

        // 通话不免费时，总通话时长为0无法发起聊天
        if ($isFree == 0 && $totalMinutes == 0) {
            throw AppException::factory(AppException::CHAT_LESS_MONTY);
        }



    }


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
}