<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\chat;

use app\common\transformer\TransformerAbstract;

class RedPackage extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "gift" => [
                "gift_name" => (string) $data["gift"]["gift_name"],
                "gift_image_url" => (string) $data["gift"]["gift_image_url"],
                "amount" => (int) $data["gift"]["amount"],
                "r_u_income" => (int) $data["gift"]["r_u_income"],
            ],
            "chat" => [
                "is_free" => (int) $data["chat"]["is_free"],
                "current_time" => (int) $data["chat"]["current_time"],
                "deadline" => (int) $data["chat"]["deadline"],
            ],
        ];

        return $returnData;
    }
}