<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\helper\Redis;
use think\facade\Db;

class RelationService extends Base
{
    /**
     * 我关注的人列表
     *
     * @param $startId int 起始ID
     * @param $pageSize int 分页
     * @param $userId int 用户ID
     * @param $retry int 尝试次数
     * @return array
     * @throws AppException
     */
    public function followList($startId, $pageSize, $userId, $retry = 0)
    {
        $redis = Redis::factory();
        // 获取缓存数据
        if ($data = getUserRelationFollowInfo($userId, $startId, $pageSize, $redis)) {
            return $data['data'];
        }

        // 加锁获取数据
        $lockKey = REDIS_USER_RELATION_FOLLOW_INFO . $userId . ":" . $startId . ":" . $pageSize . ":Lock";
        if ($redis->setnx($lockKey, 1)) {
            // 锁加过期时间防止锁死
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);

            $ret = [
                'pageInfo' => [
                    'startId' => $startId,
                    'pageSize' => $pageSize,
                    'userId' => $userId,
                ],
                'data' => [],
            ];

            // 获取当前用户关注用户 列表
            $query = Db::name("user_follow")->alias("uf")
                ->leftJoin("user u", "u.id = uf.follow_u_id")
                ->leftJoin("user_info ui", "ui.u_id = uf.follow_u_id")
                ->field("uf.id,uf.follow_u_id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
                ->where("uf.u_id", $userId);
            if (!empty($startId)) {
                $query = $query->where("uf.id", "<", $startId);
            }

            $data = $query->select()->toArray();
            $ret["data"] = $data;
            cacheUserRelationFollowInfo($userId, $startId, $pageSize, $ret, $redis);
            $redis->del($lockKey);
            return $ret['data'];
        } else {
            // 锁加过期时间防止锁死
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->followList($startId, $pageSize, $userId, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**
     * 我的粉丝列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $userId int 用户ID
     * @param int $retry 尝试次数
     * @return array
     * @throws AppException
     */
    public function fansList($startId, $pageSize, $userId, $retry = 0)
    {
        $redis = Redis::factory();
        // 获取缓存数据
        if ($data = getUserRelationFansInfo($userId, $startId, $pageSize, $redis)) {
            return $data['data'];
        }

        // 加锁获取数据
        $lockKey = REDIS_USER_RELATION_FANS_INFO . $userId . ":" . $startId . ":" . $pageSize . ":Lock";
        if ($redis->setnx($lockKey, 1)) {
            // 锁加过期时间防止锁死
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);

            $ret = [
                'pageInfo' => [
                    'startId' => $startId,
                    'pageSize' => $pageSize,
                    'userId' => $userId,
                ],
                'data' => [],
            ];

            // 获取当前用户关注用户 列表
            $query = Db::name("user_follow")->alias("uf")
                ->leftJoin("user u", "u.id = uf.u_id")
                ->leftJoin("user_info ui", "ui.u_id = uf.u_id")
                ->field("uf.id,uf.u_id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
                ->where("uf.follow_u_id", $userId);
            if (!empty($startId)) {
                $query = $query->where("uf.id", "<", $startId);
            }

            $data = $query->select()->toArray();
            $ret["data"] = $data;
            cacheUserRelationFansInfo($userId, $startId, $pageSize, $ret, $redis);
            $redis->del($lockKey);
            return $ret['data'];
        } else {
            // 锁加过期时间防止锁死
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->fansList($startId, $pageSize, $userId, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**
     * 好友列表
     *
     * @param $pageNum int 页码
     * @param $pageSize int 分页大小
     * @param $userId int 用户ID
     * @return array
     * @throws AppException
     */
    public function friendList($pageNum, $pageSize, $userId)
    {
        $redis = Redis::factory();
        // 获取缓存数据
        if ($data = getUserRelationFriendInfo($userId, $pageSize, $redis)) {
            return array_slice($data['data'], ($pageNum - 1) * $pageSize, $pageSize);
        }

        $ret = [
            'pageInfo' => [
                'pageNum' => $pageNum,
                'pageSize' => $pageSize,
                'userId' => $userId,
            ],
            'data' => [],
        ];

        // 获取当前用户关注用户id
        $followUserIds = Db::name("user_follow")
            ->where("u_id", $userId)
            ->column("follow_u_id");
        // 获取当前用户粉丝ID
        $fansUserIds = Db::name("user_follow")
            ->where("follow_u_id", $userId)
            ->column("u_id");

        $friendIds = array_intersect($followUserIds, $fansUserIds);
        if (empty($friendIds)) {
            cacheUserRelationFriendInfo($userId, $pageSize, $ret, $redis);
            return [];
        }

        $data = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->whereIn("u.id", $friendIds)
            ->select()->toArray();

        $ret["data"] = $data;
        cacheUserRelationFriendInfo($userId, $pageSize, $ret, $redis);
        return array_slice($data, ($pageNum - 1) * $pageSize, $pageSize);
    }
}