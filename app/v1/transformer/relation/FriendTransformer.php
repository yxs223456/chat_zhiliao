<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\relation;

use app\common\transformer\TransformerAbstract;

class FriendTransformer extends TransformerAbstract
{
    public function transformData(array $data): array
    {
        return [
            'u_id' => $data['id'] ?? 0,
            'avatar' => $data['portrait'] ?? '',
            'nickname' => $data['nickname'] ?? '',
            'sex' => $data['sex'] ?? 0,
            'age' => $this->getUserAge($data['birthday'] ?? ""),
            'city' => $data["city"] ?? "",
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