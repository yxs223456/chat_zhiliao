<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\chat;

use app\common\transformer\TransformerAbstract;

class Dial extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "chat_id" => (int) $data["chat_id"],
        ];

        return $returnData;
    }
}