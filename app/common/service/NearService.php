<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;

use app\common\enum\UserIsStealthEnum;
use app\common\helper\Redis;
use think\facade\Db;

class NearService extends Base
{
    /**
     * 附近人列表
     *
     * @param $pageNum int 起始ID
     * @param $pageSize int 分页
     * @param $isFlush int 是否刷新
     * @param $userId int 用户ID
     * @return array
     */
    public function user($pageNum, $pageSize, $isFlush, $userId)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->userUpdate($pageSize, $userId);
        }
        // 不需要刷新查询缓存数据
        return $this->userPage($pageNum, $pageSize, $userId);
    }

    /**
     * 刷新附近动态缓存数据
     * 1. 判断是否需要更新缓存 默认5分钟内只能刷新一次
     * 2. 需要更新缓存，删除所有缓存，添加新缓存
     * 3. 不需要更新缓存，直接获取第一页数据
     *
     * @param $pageSize int 分页大小
     * @param $userId int  用户id
     * @return array
     */
    private function userUpdate($pageSize, $userId)
    {
        // 获取更新锁 不为空5分钟内更新过，直接返回
        $redis = Redis::factory();
        $lock = getNearUserSortSetLock($userId, $redis);
        if (!empty($lock)) {
            return $this->userPage(1, $pageSize, $userId);
        }

        // 没有加锁
        // 更新前删除之前的缓存
        deleteNearUserCache($userId, $redis);
        // 缓存当前用户附近人集合缓存
        cacheNearUserSortSet($userId, $redis);
        // 添加缓存更新锁
        setNearUserSortSetLock($userId, $redis);
        return $this->userPage(1, $pageSize, $userId);
    }

    /**
     * 获取附近人的分页数据
     *
     * @param $pageNum int 分页
     * @param $pageSize int 分页大小
     * @param $userId int 当前用户id
     * @return array
     */
    private function userPage($pageNum, $pageSize, $userId)
    {
        // 读附近人分页缓存
        $redis = Redis::factory();

        $ret = [
            "userInfo" => [],
            "distance" => []
        ];

        // 缓存IDset不存在初始化
        if (!$redis->exists(REDIS_NEAR_USER_SORT_SET . $userId)) {
            // 缓存当前用户附近人集合缓存
            cacheNearUserSortSet($userId, $redis);
        }

        // 不存在缓存重新生成
        $userIds = getNearUserSortSet($userId, $pageNum, $pageSize, $redis);
        // 有序集合没有数据直接返回空数组
        if (empty($userIds)) {
            return array_values($ret);
        }

        // 查询用户数据
        $userData = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->leftJoin("user_set us", "u.id = us.u_id")
            ->field("u.id,u.sex,ui.portrait,ui.nickname,ui.birthday,
            us.voice_chat_switch,us.voice_chat_price,us.video_chat_switch,us.video_chat_price,us.direct_message_free,
            us.direct_message_price")
            ->whereIn("u.id", array_keys($userIds))
            ->where("us.is_stealth", UserIsStealthEnum::NO)
            ->select()->toArray();
        if (empty($userData)) {
            return array_values($ret);
        }

        $idToData = array_combine(array_column($userData,'id'),$userData);
        $sortUserData = [];
        foreach ($userIds as $key => $item)
        {
            $sortUserData[] = isset($idToData[$key]) ? $idToData[$key] : [];
        }

        $ret["userInfo"] = array_values(array_filter($sortUserData));
        $ret["distance"] = $userIds;

        return array_values($ret);
    }
}