<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\video;

use app\common\transformer\TransformerAbstract;

class VideoListTransformer extends TransformerAbstract
{
    // 当前用户
    private $user = null;
    // 小视频对应的点赞用户ID
    private $likeVideoUserIds = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        $this->user = $this->_queries["user"] ?? [];
        $this->likeVideoUserIds = $this->_queries["likeVideoUserIds"] ?? [];
    }

    public function transformData(array $data): array
    {
        return [
            "id" => $data["id"] ?? 0,
            "u_id" => $data["u_id"] ?? 0,
            "avatar" => (string)$data["portrait"] ?? "",
            "source" => $data["source"] ?? "",
            "like_count" => (int)$data["like_count"] ?? 0,
            "city" => $data["city"] ?? "",
            "is_like" => $this->getIsLike($data["id"]),
        ];
    }

    private function getIsLike($videoId)
    {
        if (isset($this->likeVideoUserIds[$videoId]) && in_array($this->user["id"], $this->likeVideoUserIds[$videoId])) {
            return 1;
        }
        return 0;
    }

}