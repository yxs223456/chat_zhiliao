<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\gift;

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
            "amount" => (int) $data["amount"],
            "r_u_income" => (int) $data["r_u_income"],
        ];

        return $returnData;
    }
}