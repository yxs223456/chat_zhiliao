<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\gift;

use app\common\transformer\TransformerAbstract;

class Give extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "gift_name" => (string) $data["gift_name"],
            "gift_image_url" => (string) $data["gift_image_url"],
            "gift_price" => (int) $data["gift_price"],
            "r_u_income" => (int) $data["r_u_income"],
        ];

        return $returnData;
    }
}