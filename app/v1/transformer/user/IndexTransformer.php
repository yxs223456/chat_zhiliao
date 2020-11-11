<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\enum\PrettyFemaleLevelEnum;
use app\common\enum\PrettyMaleLevelEnum;
use app\common\enum\UserIsPrettyEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;

class IndexTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $userInfo = $data["userInfo"] ?? [];
        $user = $data["user"] ?? [];
        $userSet = $data["userSet"] ?? [];
        $videoLike = $data["videoLike"] ?? [];
        $currentLikeDynamicId = $data["currentLikeDynamicId"] ?? [];
        return [
            "user" => [
                'id' => $user["id"] ?? 0,
                'sex' => $user['sex'] ?? UserSexEnum::UNKNOWN,
                'age' => $this->getUserAge($userInfo['birthday'] ?? null),
                'user_number' => $user['user_number'] ?? "",
                'avatar' => $userInfo['portrait'] ?? "",
                'nickname' => $userInfo['nickname'] ?? "",
                'birthday' => $userInfo["birthday"] ?? "",
                'photos' => empty($userInfo["photos"]) ? [] : json_decode($userInfo["photos"], true),
                'city' => empty($userInfo['city']) ? "" : CityService::getCityByCode($userInfo['city']),
                'is_pretty' => $userInfo["is_pretty"] ?? UserIsPrettyEnum::NO,
                'pretty_female_level' => $userInfo["pretty_female_level"] ?? PrettyFemaleLevelEnum::COMMON,
                'pretty_male_level' => $userInfo['pretty_male_level'] ?? PrettyMaleLevelEnum::COMMON,
                'member_level' => $userInfo['member_level'] ?? 0,
                'is_vip' => !empty($userInfo['svip_level']) ? 1 : empty($userInfo['vip_level']) ? 0 : 1,
                'is_svip' => empty($userInfo['svip_level']) ? 0 : 1,
                'signatures' => empty($userInfo['signatures']) ? [] : json_decode($userInfo['signatures'],true),
                'voice_chat_switch' => $userSet['voice_chat_switch'] ?? UserSwitchEnum::OFF,
                'voice_chat_price' => $userSet['voice_chat_price'] ?? 0,
                'video_chat_switch' => $userSet['video_chat_switch'] ?? UserSwitchEnum::OFF,
                'video_chat_price' => $userSet['video_chat_price'] ?? 0,
                'direct_message_free' => $userSet['direct_message_free'] ?? UserSwitchEnum::OFF,
                'direct_message_price' => $userSet['direct_message_price'] ?? 0,
                "score" => $data["score"] ?? "0",
            ],
            "is_followed" => $data["is_follow"] ?? 0,
            "is_blacked" => $data["is_black"] ?? 0,
            "dynamics" => $this->getDynamic($data["dynamics"], $data["dynamicLike"],$currentLikeDynamicId),// 动态
            "videos" => $this->getVideos($data['videos'] ?? [], $userInfo, $userSet, $data["is_follow"] ?? 0, $videoLike), // 视频
            "gifts" => $this->getGifts($data["gifts"] ?? []),
            "have_angle" => empty($data["guard"]) ? 0 : 1,
            "angle" => $this->getGuard($data["guard"] ?? []),
        ];
    }

    private function getDynamic($dynamic, $dynamicLike, $currentLikeDynamicId)
    {
        if (empty($dynamic)) {
            return [];
        }
        $ret = [];
        foreach ($dynamic as $item) {
            $tmp = [];
            $tmp["dynamic_id"] = $item["id"];
            $tmp["source"] = json_decode($item["source"],true);
            $tmp["like_count"] = isset($dynamicLike[$item["id"]]) ? $dynamicLike[$item['id']] : 0;
            $tmp["is_like"] = in_array($item["id"], $currentLikeDynamicId) ? 1 : 0;
            $ret[] = $tmp;
        }
        return $ret;
    }

    private function getVideos($videos, $userInfo, $userSet, $follow, $videoLike)
    {
        if (empty($videos)) {
            return [];
        }
        $ret = [];
        foreach ($videos as $item) {
            $tmp = [];
            $tmp["id"] = $item["id"];
            $tmp["u_id"] = $userInfo["u_id"];
            $tmp["avatar"] = (string)$userInfo["portrait"] ?? "";
            $tmp["cover"] = (string)$item["cover"] ?? "";
            $tmp["source"] = (string)$item["source"] ?? "";
            $tmp["like_count"] = (int)$item["like_count"] ?? 0;
            $tmp["city"] = empty($userInfo["city"]) ? "" : CityService::getCityByCode($userInfo["city"]);
            $tmp["is_like"] = in_array($item["id"], $videoLike) ? 1 : 0;
            $tmp["transcode_status"] = (int)$item["transcode_status"] ?? 0;
            $tmp["is_followed"] = (int)$follow;
            $tmp['voice_chat_switch'] = $userSet['voice_chat_switch'] ?? UserSwitchEnum::OFF;
            $tmp['voice_chat_price'] = $userSet['voice_chat_price'] ?? 0;
            $tmp['video_chat_switch'] = $userSet['video_chat_switch'] ?? UserSwitchEnum::OFF;
            $tmp['video_chat_price'] = $userSet['video_chat_price'] ?? 0;
            $tmp['direct_message_free'] = $userSet['direct_message_free'] ?? UserSwitchEnum::OFF;
            $tmp['direct_message_price'] = $userSet['direct_message_price'] ?? 0;
            $ret[] = $tmp;
        }
        return $ret;
    }

    private function getGuard($guard)
    {
        if (empty($guard)) {
            return new \stdClass();
        }
        return [
            'guard_u_id' => $guard["u_id"] ?? 0,
            'avatar' => $guard["portrait"] ?? "",
        ];
    }

    private function getGifts($gifts)
    {
        if (empty($gifts)) {
            return [];
        }
        return $gifts;
    }

    /**
     *  年龄
     *
     * @param $birthday
     * @return false|int|string
     */
    private function getUserAge($birthday)
    {
        return isset($birthday) ? date('Y') - substr($birthday, 0, 4) : 0;
    }
}
