<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\enum\UserOccupationEnum;
use app\common\transformer\TransformerAbstract;

class InfoTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            "u_id" => $data["u_id"] ?? 0,
            "avatar" => (string)$data["portrait"] ?? "",
            "nickname" => (string)$data["nickname"] ?? "",
            "birthday" => (string)$data["birthday"] ?? "",
            "occupation" => $this->getOccupation($data["occupation"]),
            "sex" => $data["sex"] ?? 0,
            "user_number" => $data["user_number"] ?? "",
            "photos" => empty($data["photos"]) ? [] : json_decode($data["photos"], true),
        ];
    }

    private function getOccupation($occupation)
    {
        return UserOccupationEnum::getEnumDescByValue($occupation);
    }
}