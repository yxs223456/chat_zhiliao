<?php

namespace app\v1\transformer\wallet;

use app\common\transformer\TransformerAbstract;

/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午4:35
 */
class PayByWeChatTransformer extends TransformerAbstract
{
    public function transformData(array $data)
    {
        return [
            "mweb_url" => (string)$data["mweb_url"],
        ];
    }
}