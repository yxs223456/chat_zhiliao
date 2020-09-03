<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\near;

use app\common\transformer\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    // 当前登陆用户ID
    private $distance = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);
        $this->distance = $this->_queries["distance"] ?? [];
    }

    public function transformData(array $data): array
    {
        return [
            'id' => $data['id'] ?? 0,
            'avatar' => $data['portrait'] ?? '',
            'nickname' => $data['nickname'] ?? '',
            'sex' => $data['sex'] ?? 0,
            'age' => $this->getUserAge($data['birthday'] ?? ""),
            'distance' => $this->getDistance($data['id']),
            'content' => $data["content"] ?? "",
            'time' => date("Y/m/d"),
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

    private function getDistance($userId)
    {
        return isset($this->distance[$userId]) ? sprintf("%.2f", $this->distance[$userId]) : "0.00";
    }
}