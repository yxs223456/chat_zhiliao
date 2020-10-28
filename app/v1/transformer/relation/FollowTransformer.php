<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\relation;

use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;

class FollowTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            'id' => $data['id'] ?? 0,
            'u_id' => $data['follow_u_id'] ?? 0,
            'avatar' => $data['portrait'] ?? '',
            'nickname' => $data['nickname'] ?? '',
            'sex' => $data['sex'] ?? 0,
            'age' => $this->getUserAge($data['birthday'] ?? ""),
            'city' => empty($data["city"]) ? "" : CityService::getCityByCode($data["city"]),
            "is_followed" => 1
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