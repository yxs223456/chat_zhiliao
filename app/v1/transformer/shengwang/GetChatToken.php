<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\shengwang;

use app\common\transformer\TransformerAbstract;

class GetChatToken extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "t_u_info" => [
                "portrait" => (string)$data["t_u_info"]["portrait"],
                "nickname" => (string)$data["t_u_info"]["nickname"],
            ],
            "sw_token_info" => [
                "token" => (string) $data["sw_token_info"]["token"],
                "u_id" => (int) $data["sw_token_info"]["u_id"],
                "channel_name" => (string) $data["sw_token_info"]["channel_name"],
                "expire" => (int) $data["sw_token_info"]["expire"],
            ],

        ];

        return $returnData;
    }
}