<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-01
 * Time: 13:59
 */

namespace app\v1\transformer\vip;

use app\common\transformer\TransformerAbstract;

class PayByWeChat extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "mweb_url" => (string) $data["mweb_url"],
        ];

        return $returnData;
    }
}