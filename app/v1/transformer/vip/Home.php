<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\vip;

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
            "user" => [],
            "svip" => [],
            "vip" => [],
        ];

        $returnData["user"] = [
            "portrait" => (string) $data["user"]["portrait"],
            "nickname" => (string) $data["user"]["nickname"],
            "sex" => (int) $data["user"]["sex"],
            "is_svip" => (int) $data["user"]["is_svip"],
            "is_vip" => (int) $data["user"]["is_vip"],
            "svip_deadline" => (string) $data["user"]["svip_deadline"],
            "vip_deadline" => (string) $data["user"]["vip_deadline"],
        ];

        foreach ($data["svip"] as $item) {
            $returnData["svip"][] = [
                "id" => (int) $item["id"],
                "name" => (string) $item["name"],
                "origin_price" => (string) $item["origin_price"],
                "price" => (string) $item["price"],
                "valid_time_desc" => (string) $item["valid_time_desc"],
                "is_hot" => (int) $item["is_hot"],
            ];
        }

        foreach ($data["vip"] as $item) {
            $returnData["vip"][] = [
                "id" => (int) $item["id"],
                "name" => (string) $item["name"],
                "origin_price" => (string) $item["origin_price"],
                "price" => (string) $item["price"],
                "valid_time_desc" => (string) $item["valid_time_desc"],
                "is_hot" => (int) $item["is_hot"],
            ];
        }

        return $returnData;
    }
}