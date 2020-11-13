<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\version;

use app\common\transformer\TransformerAbstract;

class ListTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            'version' => $data["version"] ?? "0",
            'description' => $data["description"] ?? "",
            'is_force' => $data["is_force"] ?? 0,
            'download_url' => $data["download_url"] ?? "",
            'is_update' => $data["is_update"] ?? 0
        ];
    }
}