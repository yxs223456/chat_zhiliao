<?php

namespace app\v1\transformer\recharge;

use app\common\transformer\TransformerAbstract;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午4:35
 */
class PayByAliTransformer extends TransformerAbstract
{
    public function transformData(array $data)
    {
        return [
            "h5_url" => (string)$data["url"],
        ];
    }
}