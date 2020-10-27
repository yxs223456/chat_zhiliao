<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\dynamic;

use app\common\helper\Redis;
use app\common\transformer\TransformerAbstract;
use think\facade\Db;

class PersonalTransformer extends TransformerAbstract
{
    // 用户数据
    private $userInfo = null;
    // 统计数据
    private $countInfo = null;
    // 当前登陆用户ID
    private $userId = null;
    // 当前用户点赞的动态ID
    private $likeDynamicUserIds = null;
    // 是否关注了
    private $isFollowed = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        $this->userInfo = empty($this->_queries["userInfo"]) ? [] : $this->_queries["userInfo"];
        $this->countInfo = empty($this->_queries["dynamicCount"]) ? [] :
            array_combine(array_column($this->_queries["dynamicCount"], "dynamic_id"), $this->_queries["dynamicCount"]);
        $this->userId = $this->_queries["userId"] ?? 0;
        $this->likeDynamicUserIds = $this->_queries["likeDynamicUserIds"] ?? [];
    }

    public function transformData(array $data): array
    {
        return [
            'id' => $data['id'] ?? 0,
            'u_id' => (int)$this->getUserInfo('id'),
            'avatar' => $this->getUserInfo('portrait'),
            'nickname' => $this->getUserInfo("nickname"),
            'sex' => (int)$this->getUserInfo("sex"),
            'age' => $this->getAge(),
            'distance' => (string)$this->getDistance(),
            'city' => $this->getUserInfo('city'),
            'create_time' => date("Y/m/d", strtotime($data["create_time"])),
            'content' => $data["content"] ?? "",
            'source' => json_decode($data["source"], true),
            'like_count' => (int)$this->getCountInfo($data["id"], 'like_count'),
            'comment_count' => (int)$this->getCountInfo($data['id'], 'comment_count'),
            'is_like' => $this->getIsLike($data["id"]),
            'is_followed' => $this->getIsFollow(),
        ];
    }

    /**
     * 获取用户信息
     *
     * @param $key
     * @return mixed|string
     */
    private function getUserInfo($key)
    {
        return isset($this->userInfo[$key]) ? $this->userInfo[$key] : "";
    }

    /**
     * 获取统计信息
     *
     * @param $dynamicId
     * @param $key
     * @return string
     */
    private function getCountInfo($dynamicId, $key)
    {
        return isset($this->countInfo[$dynamicId][$key]) ? $this->countInfo[$dynamicId][$key] : "";
    }

    private function getAge()
    {
        return isset($this->userInfo["birthday"]) ? date('Y') - substr($this->userInfo["birthday"], 0, 4) : 0;
    }

    private function getDistance()
    {
        if ($this->userId == $this->userInfo["id"]) {
            return 0;
        }

        $redis = Redis::factory();
        return sprintf("%.2f", getDistanceByTwoUserId($this->userId, $this->userInfo["id"], $redis));
    }

    private function getIsLike($dynamicId)
    {
        if (!isset($this->likeDynamicUserIds[$dynamicId])) {
            return 0;
        }
        return in_array($this->userId, $this->likeDynamicUserIds[$dynamicId]) ? 1 : 0;
    }

    private function getIsFollow()
    {
        if (isset($this->isFollowed)) {
            return $this->isFollowed;
        }
        if ($this->userId == $this->userInfo['id']) {
            $this->isFollowed = 0;
            return 0;
        }
        $exists = Db::name("user_follow")->where("u_id", $this->userId)
            ->where("follow_u_id", $this->userInfo['id'])
            ->field("id")->find();
        if (empty($exists)) {
            $this->isFollowed = 0;
            return 0;
        }
        $this->isFollowed = 1;
        return 1;
    }

}