<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\FollowIsFriendEnum;
use think\facade\Db;

class RelationService extends Base
{
    /**
     * 我关注的人列表
     *
     * @param $startId int 起始ID
     * @param $pageSize int 分页
     * @param $userId int 用户ID
     * @return array
     * @throws AppException
     */
    public function followList($startId, $pageSize, $userId)
    {
        // 获取当前用户关注用户 列表
        $query = Db::name("user_follow")->alias("uf")
            ->leftJoin("user u", "u.id = uf.follow_u_id")
            ->leftJoin("user_info ui", "ui.u_id = uf.follow_u_id")
            ->field("uf.id,uf.follow_u_id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->where("uf.u_id", $userId)
            ->order("uf.id","desc");
        if (!empty($startId)) {
            $query = $query->where("uf.id", "<", $startId);
        }
        return $query->limit($pageSize)->select()->toArray();
    }

    /**
     * 我的粉丝列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $userId int 用户ID
     * @return array
     * @throws AppException
     */
    public function fansList($startId, $pageSize, $userId)
    {
        // 获取当前用户粉丝 列表
        $query = Db::name("user_follow")->alias("uf")
            ->leftJoin("user u", "u.id = uf.u_id")
            ->leftJoin("user_info ui", "ui.u_id = uf.u_id")
            ->field("uf.id,uf.u_id,uf.is_friend,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->where("uf.follow_u_id", $userId)
            ->order("uf.id","desc");
        if (!empty($startId)) {
            $query = $query->where("uf.id", "<", $startId);
        }

        return $query->limit($pageSize)->select()->toArray();
    }

    /**
     * 好友列表
     *
     * @param $startId int 查询ID
     * @param $pageSize int 分页大小
     * @param $userId int 用户ID
     * @return array
     * @throws AppException
     */
    public function friendList($startId, $pageSize, $userId)
    {
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
            return [];
        }

        $query = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->whereIn("u.id", $friendIds)
            ->order("u.id", "desc");
        if ($startId) {
            $query = $query->where("u.id", "<", $startId);
        }
        $data = $query->limit($pageSize)
            ->select()->toArray();

        return $data;
    }

    /**
     * 取消用户关注
     *
     * @param $followId
     * @param $userId
     * @throws AppException
     */
    public function unfollow($followId, $userId)
    {
        if ($followId == $userId) {
            throw AppException::factory(AppException::USER_NOT_FOLLOW_SELF);
        }
        $followUser = UserService::getUserById($followId);
        if (empty($followUser)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        $isFollow = Db::name("user_follow")->where("u_id", $userId)->where("follow_u_id", $followId)->find();
        if (empty($isFollow)) {
            throw AppException::factory(AppException::USER_NOT_FOLLOW);
        }
        Db::name("user_follow")->where("u_id", $userId)->where("follow_u_id", $followId)->delete();
        $followedMe = Db::name("user_follow")->where("u_id", $followId)->where("follow_u_id", $userId)->find();
        // 当前用户也关注我，需要删除自己好友缓存
        if (!empty($followedMe)) {
            Db::name("user_follow")
                ->where("(u_id = {$followId} and follow_u_id = {$userId}) or (u_id = {$userId} and follow_u_id = {$followId})")
                ->update(["is_friend" => FollowIsFriendEnum::NO]);
        }
    }

    /**
     * 关注用户
     *
     * @param $followId
     * @param $userId
     * @throws AppException
     */
    public function follow($followId, $userId)
    {
        if ($followId == $userId) {
            throw AppException::factory(AppException::USER_NOT_FOLLOW_SELF);
        }
        $followUser = UserService::getUserById($followId);
        if (empty($followUser)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        if (BlackListService::inUserBlackList($userId, $followId)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 访问日志队列
        VisitorService::addVisitorLog($followId, $userId);
        // 添加关注
        $isFollow = Db::name("user_follow")->where("u_id", $userId)->where("follow_u_id", $followId)->find();
        if (!empty($isFollow)) {
            throw AppException::factory(AppException::USER_IS_FOLLOWED);
        }

        Db::name("user_follow")->insertGetId(["follow_u_id" => $followId, 'u_id' => $userId]);
        $followedMe = Db::name("user_follow")->where("u_id", $followId)->where("follow_u_id", $userId)->find();
        // 当前用户也关注我，更新is_friend
        if (!empty($followedMe)) {
            Db::name("user_follow")
                ->where("(u_id = {$followId} and follow_u_id = {$userId}) or (u_id = {$userId} and follow_u_id = {$followId})")
                ->update(["is_friend" => FollowIsFriendEnum::YES]);
        }
    }
}