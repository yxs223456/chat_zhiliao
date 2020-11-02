<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\video;

use app\common\transformer\TransformerAbstract;

class PersonalListTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            "id" => $data["id"] ?? 0,
            "source" => $data["source"] ?? "",
            "like_count" => (int)$data["like_count"] ?? 0,
        ];
    }

}