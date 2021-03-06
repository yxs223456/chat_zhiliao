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
use app\v1\transformer\dynamic\CommentTransformer;
use app\v1\transformer\dynamic\ConcernTransformer;
use app\v1\transformer\dynamic\InfoTransformer;
use app\v1\transformer\dynamic\NearTransformer;
use app\v1\transformer\dynamic\NewestTransformer;
use app\v1\transformer\dynamic\PersonalTransformer;

class Dynamic extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**********************************************公共动态接口开始****************************************************/
    /**
     * 最近列表 注意去重复 id倒叙
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function near()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $long = $request["long"] ?? ""; // 经度
        $lat = $request["lat"] ?? ""; // 纬度
        $isFlush = $request["is_flush"] ?? 0; // 刷新
        $userId = $this->query["user"]["id"]; // 当前登陆用户ID

        if (!checkInt($pageNum,false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        $dynamic = $service->near($pageNum, $pageSize, $long, $lat, $userId, $isFlush);
        return $this->jsonResponse($dynamic, new NearTransformer(['userId' => $this->query["user"]["id"]]));
    }

    /**
     * 最新列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function newest()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount, $likeDynamicUserIds, $userFollow) = $service->newest($startId, $pageSize, $user);
        return $this->jsonResponse($dynamic, new NewestTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount,
                'userId' => $this->query["user"]["id"], 'likeDynamicUserIds' => $likeDynamicUserIds,
                'userFollow' => $userFollow]
        ));
    }

    /**
     * 关注列表 注意去重复 id倒叙
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function concern()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $userId = $this->query["user"]['id'];
        if (!checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount, $likeDynamicUserIds) = $service->concern($startId, $pageSize, $userId);
        return $this->jsonResponse($dynamic, new ConcernTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount,
                'userId' => $this->query["user"]["id"], 'likeDynamicUserIds' => $likeDynamicUserIds]
        ));
    }

    /**
     * 点赞动态 （添加访问记录）
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function like()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;

        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        $service->like($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 动态取消点赞
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function unlike()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;

        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        $service->unlike($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 评论动态 （添加访问记录）
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

        if (!checkInt($id,false) || empty($content) || !checkInt($pid)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        $data = $service->comment($id, $pid, $content, $user);
        return $this->jsonResponse($data, new CommentTransformer());
    }

    /**
     * 动态详情 （添加访问记录）
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function info()
    {
        $request = $this->query["content"];
        $id = $request["id"] ?? 0;
        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new DynamicService();
        return $this->jsonResponse($service->info($id, $user), new InfoTransformer($user));
    }

    /**
     * 个人动态列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function personal()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $requestUserId = $request["user_id"] ?? 0;
        if (empty($requestUserId)) {
            $requestUserId = $this->query["user"]["id"];
        }

        if (!checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        list($dynamic, $userInfo, $dynamicCount, $likeDynamicUserIds) = $service->personal($startId, $pageSize, $requestUserId);
        return $this->jsonResponse($dynamic, new PersonalTransformer(
            ["userInfo" => $userInfo, 'dynamicCount' => $dynamicCount,
                'userId' => $this->query["user"]["id"], 'likeDynamicUserIds' => $likeDynamicUserIds]
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
        $source = $request["source"] ?? "";

        if (!is_string($source)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $source = array_filter(explode(",", $source));

        if (empty($content) && empty($source)) {
            throw AppException::factory(AppException::DYNAMIC_CONTENT_EMPTY);
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

        if (!checkInt($id, false)) {
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

        if (!checkInt($id,false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new DynamicService();
        $service->report($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**********************************************个人动态接口结束****************************************************/
}