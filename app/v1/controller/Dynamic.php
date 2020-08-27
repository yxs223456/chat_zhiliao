<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\DynamicService;
use app\v1\transformer\dynamic\InfoTransformer;
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
     * 最近列表 注意去重复 时间倒叙
     */
    public function near()
    {

    }

    /**
     * 最新列表 注意去重复 时间倒叙
     */
    public function newest()
    {

    }

    /**
     * 关注列表 注意去重复 时间倒叙
     */
    public function concern()
    {

    }

    /**
     * 点赞
     */
    public function like()
    {

    }

    /**
     * @param 动态id
     * @param 父评论ID
     * @param 内容
     */
    public function comment()
    {

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
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
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
        $userId = $request["user_id"] ?? 0;
        if (empty($userId)) {
            $userId = $this->query["user"]["id"];
        }

        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount) = $service->personal($startId, $pageSize, $userId);
        return $this->jsonResponse($dynamic, new PersonalTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount, 'userId' => $this->query["user"]["id"]]
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
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
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
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
        }

        $service = new DynamicService();
        $service->report($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**********************************************个人动态接口结束****************************************************/
}