<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/15
 * Time: 下午2:29
 */

namespace app\common\service;


use app\common\AppException;
use app\common\helper\Redis;
use think\facade\Db;

class BlackListService extends Base
{
    /**
     * 获取用户黑名单用户ID
     *
     * @param $userId
     * @param null $redis
     * @return array
     */
    public static function getUserBlackListById($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUserBlackList = getUserBlackListByUId($userId, $redis);
        if (!empty($cacheUserBlackList)) {
            return $cacheUserBlackList["blackUserIds"];
        }

        $blackUserIds = Db::name("black_list")->where("u_id", $userId)->column("black_u_id");
        $ret = [
            'userId' => $userId,
            'blackUserIds' => $blackUserIds
        ];
        cacheUserBlackListByUId($ret, $redis);
        return $blackUserIds;
    }

    /**
     * 判读用户是否在另一个用户黑名单 在返回true
     *
     * @param $userId int 查询的用户ID
     * @param $blackUserId int 黑名单用户ID
     * @param null $redis
     * @return bool
     */
    public static function inUserBlackList($userId, $blackUserId, $redis = null)
    {
        $blackUserIds = self::getUserBlackListById($blackUserId, $redis);
        if (in_array($userId, $blackUserIds)) {
            return true;
        }
        return false;
    }

    /**
     * 用户加入黑名单
     * 1. 判断用户存在
     * 2. 是否已存在黑名单
     * 3. 调接口
     * 4. 修改数据库
     * 5. 删除缓存
     *
     * @param $user int 用户ID
     * @param $blackUserId int 放入黑名单的用户ID
     * @throws AppException
     */
    public function addBlack($user, $blackUserId)
    {
        if ($user["id"] == $blackUserId) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $blackUserData = UserService::getUserById($blackUserId);
        if (empty($blackUserData)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }

        $exists = Db::name("black_list")->where("u_id", $user['id'])->where("black_u_id", $blackUserId)->find();
        if ($exists) {
            throw AppException::factory(AppException::USER_IS_ADD_BLACK);
        }

        Db::name("black_list")->insertGetId(["u_id" => $user["id"], "black_u_id" => $blackUserId]);
        $redis = Redis::factory();
        // 删除黑名单用户ID缓存
        deleteUserBlackListByUId($user["id"], $redis);
        // 删除黑名单用户数据缓存
        deleteUserBlackListDataByUId($user['id'], $redis);
    }

    /**
     * 从黑名单移除用户
     * 1. 是否存在黑名单
     * 2. 调用接口
     * 3. 删除数据库
     * 4. 删缓存
     *
     * @param $user int 用户ID
     * @param $blackUserId int 放入黑名单的用户ID
     * @throws AppException
     */
    public function removeBlack($user, $blackUserId)
    {
        if ($user["id"] == $blackUserId) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $exists = Db::name("black_list")->where("u_id", $user['id'])->where("black_u_id", $blackUserId)->find();
        if (!$exists) {
            throw AppException::factory(AppException::USER_IS_REMOVE_BLACK);
        }

        Db::name("black_list")->where("u_id", $user["id"])->where("black_u_id", $blackUserId)->delete();
        $redis = Redis::factory();
        // 删除用户黑名单ID缓存
        deleteUserBlackListByUId($user['id'], $redis);
        // 删除用户黑名单用户数据缓存
        deleteUserBlackListDataByUId($user['id'], $redis);
    }

    /**
     * 获取用户黑名单列表
     *
     * @param $user
     * @return mixed
     */
    public function list($user)
    {
        $redis = Redis::factory();
        $userBlackList = getUserBlackListDataByUId($user['id'], $redis);
        if (!empty($userBlackList)) {
            return $userBlackList["list"];
        }

        $ret = [
            "userId" => $user["id"],
            "list" => []
        ];

        $blackUserIds = self::getUserBlackListById($user["id"]);
        if (empty($blackUserIds)) {
            cacheUserBlackListDataByUId($ret, $redis);
            return [];
        }
        $list = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->whereIn("u.id", $blackUserIds)
            ->select()->toArray();

        $ret["list"] = $list;
        cacheUserBlackListDataByUId($ret, $redis);
        return $list;
    }
}