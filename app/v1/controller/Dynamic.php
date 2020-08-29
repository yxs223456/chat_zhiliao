<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\helper\Redis;
use app\common\service\DynamicService;
use app\v1\transformer\dynamic\InfoTransformer;
use app\v1\transformer\dynamic\NewestTransformer;
use app\v1\transformer\dynamic\PersonalTransformer;

class Dynamic extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];

    /**********************************************公共动态接口开始****************************************************/

    /**
     * 最近列表 注意去重复 id倒叙
     */
    public function near()
    {

    }

    /**
     * 最新列表
     *
     * @return \think\response\Json
     */
    public function newest()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $isFlush = $request["is_flush"] ?? 0; // 是否刷新 0-否，1-是
        $user = $this->query["user"];

        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount, $likeDynamicId) = $service->newest($startId, $pageSize, $isFlush, $user);
        return $this->jsonResponse($dynamic, new NewestTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount,
                'userId' => $this->query["user"]["id"], 'likeDynamicId' => $likeDynamicId]
        ));
    }

    /**
     * 关注列表 注意去重复 id倒叙
     */
    public function concern()
    {

    }

    /**
     * 点赞动态
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function like()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;

        if (empty($id)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        $service->like($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 评论动态
     *
     * @param 动态id
     * @param 父评论ID
     * @param 内容
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function comment()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;
        $pid = $request["pid"] ?? 0;
        $content = $request["content"] ?? "";

        if (empty($id) || empty($content)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        $service->comment($id, $pid, $content, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 动态详情
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function info()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;
        if (empty($id)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        return $this->jsonResponse($service->info($id), new InfoTransformer($user));
    }

    /**
     * 个人动态列表
     *
     * @return \think\response\Json
     */
    public function personal()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $requestUserId = $request["user_id"] ?? 0;
        if (empty($userId)) {
            $requestUserId = $this->query["user"]["id"];
        }
        $currentUserId = $this->query["user"]["id"];

        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount, $likeDynamicId) = $service->personal($startId, $pageSize, $requestUserId, $currentUserId);
        return $this->jsonResponse($dynamic, new PersonalTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount,
                'userId' => $this->query["user"]["id"], 'likeDynamicId' => $likeDynamicId]
        ));
    }

    /**********************************************公共动态接口开始****************************************************/
    /**********************************************个人动态接口开始****************************************************/

    /**
     * 发布动态
     *
     * @param 文字内容
     * @param 媒体内容
     *
     * @return \stdClass
     * @throws AppException
     */
    public function post()
    {
        $request = $this->query["content"];
        $content = $request["content"] ?? "";
        $source = $request["source"] ?? [];

        if (empty($content) && empty($source)) {
            throw AppException::factory(AppException::USER_DYNAMIC_CONTENT_EMPTY);
        }
        $user = $this->query["user"];
        $service = new DynamicService();
        $service->post($content, $source, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 删除动态
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function delete()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;
        $user = $this->query["user"];

        if (empty($id)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        $service->delete($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 举报动态
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function report()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;
        $user = $this->query['user'];

        if (empty($id)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        $service->report($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**********************************************个人动态接口结束****************************************************/
}