<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\chat;

use app\common\transformer\TransformerAbstract;

class Dial extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "chat_id" => (int) $data["chat_id"],
            "sw_token_info" => [
                "token" => (string) $data["sw_token_info"]["sw_token_info"]["token"],
                "u_id" => (int) $data["sw_token_info"]["u_id"],
                "channel_name" => (string) $data["sw_token_info"]["channel_name"],
                "expire" => (int) $data["sw_token_info"]["expire"],
            ],
        ];

        return $returnData;
    }
}