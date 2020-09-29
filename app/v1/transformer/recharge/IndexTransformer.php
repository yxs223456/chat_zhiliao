<?php

namespace app\v1\transformer\recharge;

use app\common\transformer\TransformerAbstract;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午4:35
 */
class IndexTransformer extends TransformerAbstract
{
    public function transformData(array $data)
    {
        return [
            'id' => $data['id'] ?? 0,
            'coin_price' => (int)$data["coin_price"] ?? 0,
            'price' => (int)$data["price"] ?? 0,
            'is_new' => $data["is_new"] ?? 0,
            'is_wechat' => $data["is_wechat"] ?? 0,
            'is_alipay' => $data["is_alipay"] ?? 0,
            'gaving' => (int)$data["gaving"] ?? 0
        ];
    }
}