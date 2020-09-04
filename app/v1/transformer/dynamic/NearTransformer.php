<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\dynamic;

use app\common\transformer\TransformerAbstract;

class NearTransformer extends TransformerAbstract
{
    // 当前登陆用户ID
    private $userId = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);
        $this->userId = $this->_queries["userId"] ?? 0;
    }

    public function transformData(array $data): array
    {
        return [
            'id' => $data['id'] ?? 0,
            'avatar' => $data['userInfo']['portrait'] ?? '',
            'nickname' => $data['userInfo']['nickname'] ?? '',
            'sex' => $data['userInfo']['sex'] ?? 0,
            'age' => $this->getUserAge($data['userInfo']['birthday'] ?? ""),
            'city' => $data['userInfo']['city'] ?? '',
            'distance' => sprintf("%.2f",$data['distance']),
            'create_time' => date("Y/m/d", strtotime($data["create_time"])),
            'content' => $data["content"] ?? "",
            'source' => json_decode($data["source"], true),
            'like_count' => $data["dynamicCount"]['like_count'] ?? 0,
            'comment_count' => $data['dynamicCount']['comment_count'] ?? 0,
            'is_like' => $this->getIsLike($data["likeDynamicUserIds"]),
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

    private function getIsLike($likeDynamicUserIds)
    {
        if (empty($likeDynamicUserIds)) {
            return 0;
        }
        return in_array($this->userId, $likeDynamicUserIds) ? 1 : 0;
    }

}