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

class BlackListTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            "id" => $data["id"] ?? 0,
            "avatar" => (string)$data["portrait"] ?? "",
            "nickname" => (string)$data["nickname"] ?? "",
            'sex' => $data['sex'] ?? 0,
            'age' => $this->getUserAge($data['birthday'] ?? ""),
            'city' => $data["city"]
        ];
    }

    /**
     * 获取年龄
     *
     * @param $birthday
     * @return false|int|string
     */
    private function getUserAge($birthday)
    {
        if (empty($birthday)) {
            return 0;
        }
        return date('Y') - substr($birthday, 0, 4);
    }
}