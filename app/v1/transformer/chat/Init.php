<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 16:29
 */

namespace app\v1\transformer\chat;

use app\common\transformer\TransformerAbstract;

class Init extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "is_free" => (int) $data["is_free"],
            "chat_id" => (int) $data["chat_id"],
            "free_minutes" => (int) $data["free_minutes"],
            "total_minutes" => (int) $data["total_minutes"],
        ];

        return $returnData;
    }
}