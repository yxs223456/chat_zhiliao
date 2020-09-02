<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\banner;

use app\common\transformer\TransformerAbstract;

class Home extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        $returnData = [
        ];

        foreach ($data as $item) {
            $link = [];
            foreach ($item["link"] as $key => $value) {
                $link[$key] = (string) $value;
            }
            $params = [];
            foreach ($item["params"] as $key => $value) {
                $params[$key] = (string) $value;
            }
            $returnData[] = [
                "id" => (int) $item["id"],
                "image_url" => (string) $item["image_url"],
                "link_type" => (int) $item["link_type"],
                "link" => $link,
                "params" => $params,
                "description" => (string) $item["description"],
            ];
        }

        return $returnData;
    }
}