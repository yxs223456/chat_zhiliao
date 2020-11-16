<?php

namespace app\v1\transformer\wallet;

use app\common\transformer\TransformerAbstract;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午4:35
 */
class WithdrawInfoTransformer extends TransformerAbstract
{
    public function transformData(array $data)
    {
        return [
            "income_amount" => (int)$data["income_amount"],
            "money_amount" => (string)$data["money_amount"],
        ];
    }
}