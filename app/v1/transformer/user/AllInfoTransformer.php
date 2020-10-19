<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\enum\CertificateStatusEnum;
use app\common\enum\PrettyFemaleLevelEnum;
use app\common\enum\PrettyMaleLevelEnum;
use app\common\enum\UserIsPrettyEnum;
use app\common\enum\UserIsStealthEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\transformer\TransformerAbstract;

class AllInfoTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $user = $data["user"] ?? [];
        $userInfo = $data["userInfo"] ?? [];
        $userSet = $data["userSet"] ?? [];
        return [
            "user" => [ // 用户数据
                'id' => $user["id"] ?? 0,
                'mobile_phone_area' => $user["mobile_phone_area"] ?? "",
                'mobile_phone' => $user["mobile_phone"] ?? "",
                'sex' => $user['sex'] ?? UserSexEnum::UNKNOWN,
                'user_number' => $user['user_number'] ?? "",
                'token' => $user['token'] ?? "",
                'avatar' => $userInfo['portrait'] ?? "",
                'nickname' => $userInfo['nickname'] ?? "",
                'birthday' => $userInfo["birthday"] ?? "",
                'photos' => empty($userInfo["photos"]) ? [] : json_decode($userInfo["photos"], true),
                'city' => $userInfo['city'] ?? "",
                'is_pretty' => $userInfo["is_pretty"] ?? UserIsPrettyEnum::NO,
                'pretty_female_level' => $userInfo["pretty_female_level"] ?? PrettyFemaleLevelEnum::COMMON,
                'pretty_male_level' => $userInfo['pretty_male_level'] ?? PrettyMaleLevelEnum::COMMON,
                'member_level' => $userInfo['member_level'] ?? 0,
                'vip_level' => $userInfo['vip_level'] ?? 0,
                'svip_level' => $userInfo['svip_level'] ?? 0,
                'signatures' => $userInfo['signatures'] ?? "",
                'vip_deadline' => $userInfo['vip_deadline'] ?? "",
                'svip_deadline' => $userInfo['svip_deadline'] ?? "",
                'certificate_status' => $userInfo['certificate_status'] ?? CertificateStatusEnum::NONE,
                'certificate_time' => empty($userInfo['certificate_time']) ? "" : date("Y-m-d H:i:s",$userInfo['certificate_time']),
                'last_login_time' => empty($userInfo['last_login_time']) ? "" : date("Y-m-d H:i:s",$userInfo['last_login_time']),
                'voice_chat_switch' => $userSet['voice_chat_switch'] ?? UserSwitchEnum::OFF,
                'voice_chat_price' => $userSet['voice_chat_price'] ?? 0,
                'video_chat_switch' => $userSet['video_chat_switch'] ?? UserSwitchEnum::OFF,
                'video_chat_price' => $userSet['video_chat_price'] ?? 0,
                'direct_message_free' => $userSet['direct_message_free'] ?? UserSwitchEnum::OFF,
                'direct_message_price' => $userSet['direct_message_price'] ?? 0,
                'is_stealth' => $userSet['is_stealth'] ?? UserIsStealthEnum::NO,
            ]
        ];
    }

}
