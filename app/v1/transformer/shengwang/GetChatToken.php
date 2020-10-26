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
            "token" => (string) $data["token"],
            "u_id" => (int) $data["u_id"],
            "channel_name" => (string) $data["channel_name"],
            "expire" => (int) $data["expire"],
        ];

        return $returnData;
    }
}