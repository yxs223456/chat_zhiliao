<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\gift;

use app\common\transformer\TransformerAbstract;

class GetAll extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
            "gift" => [

            ],
        ];

        foreach ($data["gift"] as $item) {
            $returnData["gift"][] = [
                "id" => (int) $item["id"],
                "name" => (string) $item["name"],
                "image_url" => (string) $item["image_url"],
                "price" => (int) $item["price"],
            ];
        }

        return $returnData;
    }
}