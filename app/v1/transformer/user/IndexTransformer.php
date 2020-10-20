<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\transformer\TransformerAbstract;

class IndexTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            "score" => $data["score"] ?? "0",
            "dynamics" => $this->getDynamic($data["dynamics"], $data["dynamicLike"]),// 动态
            "videos" => $this->getVideos($data['videos'] ?? []), // 视频
            "gifts" => $this->getGifts($data["gifts"] ?? []),
            "guard" => $this->getGuard($data["guard"] ?? []),
        ];
    }

    private function getDynamic($dynamic, $dynamicLike)
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
            $ret[] = $tmp;
        }
        return $ret;
    }

    private function getVideos($videos)
    {
        if (empty($videos)) {
            return [];
        }
        $ret = [];
        foreach ($videos as $item) {
            $tmp = [];
            $tmp["id"] = $item["id"];
            $tmp["source"] = $item["source"];
            $tmp["like_count"] = $item["like_count"];
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
}
