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
            "user" => [ // 用户数据
                "id" => $data["user"]["id"] ?? 0,
                "avatar" => $data["userInfo"]["portrait"] ?? "",
                "nickname" => $data["userInfo"]["nickname"] ?? "",
                "photos" => empty($data["userInfo"]["photos"]) ? [] : json_decode($data["userInfo"]["photos"], true),
                "signature" => $data["userInfo"]["personal_signature"] ?? "",
                "age" => $this->getUserAge($data["userInfo"]["birthday"]),
                "sex" => $data["user"]["sex"] ?? 0,
                "city" => $data["userInfo"]["city"] ?? "",
                "vip_level" => $data["userInfo"]["vip_level"] ?? 0,
                "svip_level" => $data["userInfo"]["svip_level"] ?? 0,
                "user_number" => $data["user"]["user_number"] ?? ""
            ],
            "dynamics" => $this->getDynamic($data["dynamics"], $data["dynamicLike"]),// 动态
            "videos" => [ // 视频

            ],
            "gifts" => [ // 礼物

            ],
            "guard" => [ // 守护

            ]
        ];
    }

    private function getUserAge($birthday)
    {
        return empty($birthday) ? 0 : date('Y') - substr($birthday, 0, 4);
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
            $tmp["like"] = isset($dynamicLike[$item["id"]]) ? $dynamicLike[$item['id']] : 0;
            $ret[] = $tmp;
        }
        return $ret;
    }
}
