<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\video;

use app\common\enum\VideoIsTransCodeEnum;
use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;

class VideoListTransformer extends TransformerAbstract
{
    // 当前用户
    private $user = null;
    // 小视频对应的点赞用户ID
    private $likeVideoUserIds = null;
    // 用户设置数据
    private $userSetData = null;
    // 登陆用户关注小视频用户的ID数组
    private $userFollow = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        $this->user = $this->_queries["user"] ?? [];
        $this->likeVideoUserIds = $this->_queries["likeVideoUserIds"] ?? [];
        $this->userSetData = $this->_queries["userSetData"] ?? [];
        $this->userFollow = $this->_queries["userFollow"] ?? [];

    }

    public function transformData(array $data): array
    {
        return [
            "id" => $data["id"] ?? 0,
            "u_id" => $data["u_id"] ?? 0,
            "avatar" => (string)$data["portrait"] ?? "",
            "cover" => (string)$data["cover"] ?? "",
            "source" => $data["source"] ?? "",
            "like_count" => (int)$data["like_count"] ?? 0,
            "city" => empty($data["city"]) ? "" : CityService::getCityByCode($data['city']),
            "is_like" => $this->getIsLike($data["id"]),
            "transcode_status" => VideoIsTransCodeEnum::SUCCESS,
            "is_followed" => $this->getIsFollow($data["u_id"]),
            "voice_chat_switch" => (int)$this->getUserSet($data["u_id"], "voice_chat_switch"),
            "voice_chat_price" => (int)$this->getUserSet($data["u_id"], "voice_chat_price"),
            "video_chat_switch" => (int)$this->getUserSet($data["u_id"], "video_chat_switch"),
            "video_chat_price" => (int)$this->getUserSet($data["u_id"], "video_chat_price"),
            "direct_message_free" => (int)$this->getUserSet($data["u_id"], "direct_message_free"),
            "direct_message_price" => (int)$this->getUserSet($data["u_id"], "direct_message_price"),
        ];
    }

    private function getIsLike($videoId)
    {
        if (isset($this->likeVideoUserIds[$videoId]) && in_array($this->user["id"], $this->likeVideoUserIds[$videoId])) {
            return 1;
        }
        return 0;
    }

    /**
     * 获取用户设置数据
     *
     * @param $uid
     * @param $key
     * @return string
     */
    private function getUserSet($uid, $key)
    {
        if (isset($this->userSetData[$uid])) {
            return $this->userSetData[$uid][$key];
        }
        return "";
    }

    private function getIsFollow($uid)
    {
        if (in_array($uid, $this->userFollow)) {
            return 1;
        }
        return 0;
    }

}