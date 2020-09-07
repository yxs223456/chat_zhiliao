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
use League\Geotools\Coordinate\Coordinate;

class NewestTransformer extends TransformerAbstract
{
    // 用户数据
    private $userInfo = null;
    // 统计数据
    private $countInfo = null;
    // 当前登陆用户ID
    private $userId = null;
    // 动态对应的点赞用户ID
    private $likeDynamicUserIds = null;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        $this->userInfo = empty($this->_queries["userInfo"]) ? [] :
            array_combine(array_column($this->_queries["userInfo"], "id"), $this->_queries["userInfo"]);
        $this->countInfo = empty($this->_queries["dynamicCount"]) ? [] :
            array_combine(array_column($this->_queries["dynamicCount"], "dynamic_id"), $this->_queries["dynamicCount"]);
        $this->userId = $this->_queries["userId"] ?? 0;
        $this->likeDynamicUserIds = $this->_queries["likeDynamicUserIds"] ?? [];
    }

    public function transformData(array $data): array
    {
        return [
            'id' => $data['id'] ?? 0,
            'avatar' => $this->getUserInfo($data['u_id'], 'portrait'),
            'nickname' => $this->getUserInfo($data['u_id'], "nickname"),
            'sex' => (int)$this->getUserInfo($data['u_id'], "sex"),
            'age' => $this->getUserAge($data['u_id']),
            'city' => $this->getUserInfo($data['u_id'], 'city'),
            'distance' => (string)$this->getDistance($data['u_id']),
            'create_time' => date("Y/m/d", strtotime($data["create_time"])),
            'content' => $data["content"] ?? "",
            'source' => json_decode($data["source"], true),
            'like_count' => (int)$this->getCountInfo($data["id"], 'like_count'),
            'comment_count' => (int)$this->getCountInfo($data['id'], 'comment_count'),
            'is_like' => $this->getIsLike($data["id"]),
        ];
    }

    /**
     * 获取用户信息
     *
     * @param $userId
     * @param $key
     * @return mixed|string
     */
    private function getUserInfo($userId, $key)
    {
        return isset($this->userInfo[$userId][$key]) ? $this->userInfo[$userId][$key] : "";
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

    private function getUserAge($userId)
    {
        return isset($this->userInfo[$userId]["birthday"]) ? date('Y') - substr($this->userInfo[$userId]["birthday"], 0, 4) : 0;
    }

    private function getDistance($userId)
    {
        if ($this->userId == $this->userInfo[$userId]["id"]) {
            return 0;
        }

        $redis = Redis::factory();
        // 获取当前登陆用户和动态用户的geohash
        $dynamicUser = getUserLongLatInfo($this->userInfo[$userId]['id'], $redis);
        $loginUser = getUserLongLatInfo($this->userId, $redis);
        if (empty($dynamicUser) || empty($loginUser)) {
            return 0;
        }

        $geotools = app('geotools');
        $decodedDynamicUser = $geotools->geohash()->decode($dynamicUser);
        $userLat = $decodedDynamicUser->getCoordinate()->getLatitude();
        $userLong = $decodedDynamicUser->getCoordinate()->getLongitude();
        $dynamicCoordUser = new Coordinate([$userLat, $userLong]);

        $loginUser = $geotools->geohash()->decode($loginUser);
        $lat = $loginUser->getCoordinate()->getLatitude();
        $long = $loginUser->getCoordinate()->getLongitude();
        $loginCoordUser = new Coordinate([$lat, $long]);

        $distance = $geotools->distance()->setFrom($dynamicCoordUser)->setTo($loginCoordUser);
        return sprintf("%.2f", $distance->in('km')->haversine());
    }

    private function getIsLike($dynamicId)
    {
        if (!isset($this->likeDynamicUserIds[$dynamicId])) {
            return 0;
        }
        return in_array($this->userId, $this->likeDynamicUserIds[$dynamicId]) ? 1 : 0;
    }

}