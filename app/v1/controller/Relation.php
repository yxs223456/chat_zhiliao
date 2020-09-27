<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\RelationService;
use app\v1\transformer\relation\FansTransformer;
use app\v1\transformer\relation\FriendTransformer;
use app\v1\transformer\relation\FollowTransformer;

class Relation extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**
     * 我的关注列表 (倒叙)
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function followList()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $userId = $this->query["user"]["id"]; // 当前登陆用户ID

        if (!checkInt($startId) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new RelationService();
        $data = $service->followList($startId, $pageSize, $userId);
        return $this->jsonResponse($data, new FollowTransformer());
    }

    /**
     * 我的粉丝列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function fansList()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $userId = $this->query["user"]["id"]; // 当前登陆用户ID

        if (!checkInt($startId) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new RelationService();
        $data = $service->fansList($startId, $pageSize, $userId);
        return $this->jsonResponse($data, new FansTransformer());
    }

    /**
     * 好友列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function friendList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $userId = $this->query['user']['id']; // 登陆用户

        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new RelationService();
        $data = $service->friendList($pageNum, $pageSize, $userId);
        return $this->jsonResponse($data, new FriendTransformer());
    }

    /**
     * 关注 （添加访问记录）
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function follow()
    {
        $request = $this->query["content"];
        $followUserId = $request["u_id"] ?? 0;
        $userId = $this->query['user']['id']; // 登陆用户

        if (!checkInt($followUserId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new RelationService();
        $service->follow($followUserId, $userId);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 取消关注
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function unFollow()
    {
        $request = $this->query["content"];
        $followUserId = $request["u_id"] ?? 0;
        $userId = $this->query['user']['id']; // 登陆用户

        if (!checkInt($followUserId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new RelationService();
        $service->unfollow($followUserId, $userId);
        return $this->jsonResponse(new \stdClass());
    }

}