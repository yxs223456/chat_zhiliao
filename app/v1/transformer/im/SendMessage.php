<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\im;

use app\common\transformer\TransformerAbstract;

class SendMessage extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "is_free" => (int) $data["is_free"],
            "price" => (int) $data["price"],
            "r_u_income" => (int) $data["r_u_income"],
        ];

        return $returnData;
    }
}