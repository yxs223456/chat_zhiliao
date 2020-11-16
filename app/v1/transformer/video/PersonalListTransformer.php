<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\video;

use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;

class PersonalListTransformer extends TransformerAbstract
{

    // 当前用户
    private $user = null;
    // 小视频对应的点赞用户ID
    private $currentUserLikeVideos = null;
    // 用户设置数据
    private $userSetData = null;
    // 登陆用户关注小视频用户的ID数组
    private $userFollow = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        $this->user = $this->_queries["user"] ?? [];
        $this->currentUserLikeVideos = $this->_queries["currentUserLikeVideos"] ?? [];
        $this->userSetData = $this->_queries["userSetData"] ?? [];
        $this->userFollow = $this->_queries["userFollow"] ?? 0;

    }

    public function transformData(array $data): array
    {
        return [
            "id" => $data["id"] ?? 0,
            "u_id" => $data["u_id"] ?? 0,
            "avatar" => (string)$data["portrait"] ?? "",
            "nickname" => (string)$data["nickname"] ?? "",
            "cover" => (string)$data["cover"] ?? "",
            "source" => (string)$data["source"] ?? "",
            "like_count" => (int)$data["like_count"] ?? 0,
            "city" => empty($data["city"]) ? "" : CityService::getCityByCode($data['city']),
            "is_like" => $this->getIsLike($data["id"]),
            "transcode_status" => (int)$data["transcode_status"] ?? 0,
            "is_followed" => (int)$this->userFollow,
            "voice_chat_switch" => (int)$this->getUserSet("voice_chat_switch"),
            "voice_chat_price" => (int)$this->getUserSet("voice_chat_price"),
            "video_chat_switch" => (int)$this->getUserSet("video_chat_switch"),
            "video_chat_price" => (int)$this->getUserSet("video_chat_price"),
            "direct_message_free" => (int)$this->getUserSet("direct_message_free"),
            "direct_message_price" => (int)$this->getUserSet("direct_message_price"),
        ];
    }

    private function getIsLike($videoId)
    {
        if (in_array($videoId, $this->currentUserLikeVideos)) {
            return 1;
        }
        return 0;
    }

    /**
     * 获取用户设置数据
     *
     * @param $key
     * @return string
     */
    private function getUserSet($key)
    {
        if (isset($this->userSetData)) {
            return $this->userSetData[$key];
        }
        return "";
    }
}