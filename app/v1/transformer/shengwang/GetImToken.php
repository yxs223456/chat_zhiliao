<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\shengwang;

use app\common\transformer\TransformerAbstract;

class GetImToken extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "token" => (string) $data["token"],
            "u_id" => (string) $data["u_id"],
            "expire" => (int) $data["expire"],
        ];

        return $returnData;
    }
}