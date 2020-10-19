<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\transformer\TransformerAbstract;

class WalletTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            'u_id' => $data['u_id'] ?? 0,
            'income_amount' => $data["income_amount"] ?? 0,
            'balance_amount' => $data["balance_amount"] ?? 0,
            'total_balance' => $data["total_balance"] ?? 0,
            'income_total_amount' => $data['income_total_amount'] ?? 0,
            'recharge_amount' => $data['recharge_amount'] ?? 0
        ];
    }
}
