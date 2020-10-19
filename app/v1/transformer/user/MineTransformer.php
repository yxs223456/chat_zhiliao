<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\enum\UserIsPrettyEnum;
use app\common\enum\UserIsStealthEnum;
use app\common\enum\UserSwitchEnum;
use app\common\transformer\TransformerAbstract;

class MineTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $user = $data["user"] ?? [];
        $userInfo = $data["userInfo"] ?? [];
        $userSet = $data['userSet'] ?? [];
        $userWallet = $data["userWallet"] ?? [];
        return [
            "userInfo" => [ // 用户数据
                "id" => $user["id"] ?? 0,
                "avatar" => $userInfo["portrait"] ?? "",
                "nickname" => $userInfo["nickname"] ?? "",
                "age" => $this->getUserAge($data["userInfo"]["birthday"]),
                "sex" => $user["sex"] ?? 0,
                "city" => $userInfo["city"] ?? "",
                "is_pretty" => $userInfo["is_pretty"] ?? UserIsPrettyEnum::NO,
                "is_vip" => $userInfo["vip_level"] ?? 0,
                "is_svip" => $userInfo["svip_level"] ?? 0,
                "svip_deadline" => $userInfo["svip_deadline"] ?? "",
                "vip_deadline" => $userInfo["vip_deadline"] ?? "",
                "user_number" => $user["user_number"] ?? ""
            ],
            "user_set" => [
                'voice_chat_switch' => $userSet["voice_chat_switch"] ?? UserSwitchEnum::OFF,
                'voice_chat_price'  => $userSet["voice_chat_price"] ?? 0,
                'video_chat_switch' => $userSet["video_chat_switch"] ?? UserSwitchEnum::OFF,
                'video_chat_price'  => $userSet["video_chat_price"] ?? 0,
                'direct_message_free' => $userSet["direct_message_free"] ?? UserSwitchEnum::OFF,
                'direct_message_price' => $userSet["direct_message_price"] ?? 0,
                'is_stealth' => $userSet["is_stealth"] ?? UserIsStealthEnum::NO
            ],
            "wallet" => [ // 视频
                'income_amount' => $userWallet["income_amount"] ?? 0,
                'balance_amount' => $userWallet["balance_amount"] ?? 0,
                'total_balance' => $userWallet["total_balance"] ?? 0
            ]
        ];
    }

    private function getUserAge($birthday)
    {
        return empty($birthday) ? 0 : date('Y') - substr($birthday, 0, 4);
    }

}
