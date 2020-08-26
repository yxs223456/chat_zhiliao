<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;


use app\common\AppException;
use app\common\enum\DbDataIsDeleteEnum;
use think\facade\Db;

class DynamicService extends Base
{

    /**
     * 发布动态
     *
     * @param $content
     * @param $source
     * @param $user
     * @return int|string
     * @throws \Throwable
     */
    public function post($content, $source, $user)
    {
        Db::startTrans();
        try {
            // 添加动态
            $dynamicData = [
                'u_id' => $user["id"],
                'content' => $content,
                'source' => json_encode($source),
                'create_time' => date("Y-m-d H:i:s")
            ];
            $id = Db::name("dynamic")->insertGetId($dynamicData);
            $dynamicCountData = [
                'u_id' => $user["id"],
                'user_dynamic_id' => $id,
                'create_time' => date("Y-m-d H:i:s")
            ];
            Db::name("dynamic_count")->insert($dynamicCountData);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $id;
    }

    /**
     * 删除动态
     *
     * @param $id
     * @param $user
     * @return int
     * @throws AppException
     */
    public function delete($id, $user)
    {
        $dynamic = Db::name("dynamic")->where("id", $id)->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
        }

        if ($dynamic["u_id"] != $user["id"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        return Db::name("dynamic")->where("id", $id)->update(["is_delete" => DbDataIsDeleteEnum::YES]);

    }

    /**
     * 获取用户动态列表
     *
     * @param $startId
     * @param $pageSize
     * @param $userId
     * @return array
     */
    public function personal($startId, $pageSize, $userId)
    {
        $ret = [
            "dynamic" => [],
            "userInfo" => [],
            "dynamicCount" => []
        ];
        // 获取动态数据
        $dynamicQuery = Db::name("dynamic")
            ->where("u_id", $userId)
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->order("create_time", "desc");
        if (!empty($startId)) {
            $dynamicQuery = $dynamicQuery->where("id", "<", $startId);
        }
        $dynamics = $dynamicQuery->limit($pageSize)->select()->toArray();

        if (empty($dynamics)) {
            return $ret;
        }
        $ret["dynamic"] = $dynamics;

        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday")
            ->whereIn("u.id", array_column($dynamics, 'u_id'))
            ->select()->toArray();
        $ret["userInfo"] = $userInfo;

        $dynamicCount = Db::name("dynamic_count")
            ->whereIn("user_dynamic_id", array_column($dynamics, 'id'))
            ->select()->toArray();
        $ret["dynamicCount"] = $dynamicCount;

        return $ret;
    }
}