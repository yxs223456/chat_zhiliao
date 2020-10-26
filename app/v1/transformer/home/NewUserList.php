<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:53
 */

namespace app\v1\transformer\home;

use app\common\transformer\TransformerAbstract;

class NewUserList extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "list" => [],
        ];

        foreach ($data["list"] as $item) {
            $returnData["list"][] = [
                "id" => (int) $item["id"],
                "user_number" => (string) $item["user_number"],
                "photo" => (string) $item["photo"],
                "city" => (string) $item["city"],
                "signature" => (string) $item["signature"],
                "video_chat_switch" => (int) $item["video_chat_switch"],
                "video_chat_price" => (int) $item["video_chat_price"],
                "voice_chat_switch" => (int) $item["voice_chat_switch"],
                "voice_chat_price" => (int) $item["voice_chat_price"],
                "score" => (string) $item["score"],
            ];
        }

        return $returnData;
    }
}