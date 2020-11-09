<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\VideoService;
use app\v1\transformer\video\PersonalListTransformer;
use app\v1\transformer\video\VideoListTransformer;

class Video extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /************************************************公共动态接口开始****************************************************/

    /**
     * 城市列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function cityList()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $city = $request["city"] ?? "";
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($startId) || empty($city)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new VideoService();
        list($video, $likeVideoUserIds, $userSetData, $userFollow) = $service->cityList($startId, $pageSize, $city, $user);
        return $this->jsonResponse($video, new VideoListTransformer(
            ['user' => $user, 'likeVideoUserIds' => $likeVideoUserIds,
                "userSetData" => $userSetData, 'userFollow' => $userFollow]
        ));
    }

    /**
     * 推荐列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function recommendList()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($startId)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new VideoService();
        list($video, $likeVideoUserIds, $userSetData, $userFollow) = $service->recommendList($startId, $pageSize, $user);
        return $this->jsonResponse($video, new VideoListTransformer(
            ['user' => $user, 'likeVideoUserIds' => $likeVideoUserIds,
                "userSetData" => $userSetData, 'userFollow' => $userFollow]
        ));
    }

    /**
     * 个人小视频列表
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

        if (!checkInt($pageSize, false) || !checkInt($requestUserId, true) || !checkInt($startId, true)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new VideoService();
        list($video, $currentUserLikeVideos, $userSetData, $userFollow) =
            $service->personal($startId, $pageSize, $requestUserId, $this->query["user"]['id']);
        return $this->jsonResponse($video, new PersonalListTransformer([
            'currentUserLikeVideos' => $currentUserLikeVideos,
            "userSetData" => $userSetData, 'userFollow' => $userFollow
        ]));
    }

    /**
     * 点赞小视频 （添加访问记录）
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
        $service = new VideoService();
        $service->like($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 小视频取消点赞
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
        $service = new VideoService();
        $service->unlike($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**********************************************公共动态接口开始****************************************************/
    /**********************************************个人动态接口开始****************************************************/

    /**
     * 发布小视频
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function post()
    {
        $request = $this->query["content"];
        $cover = $request["cover"] ?? ""; //小视频封面图片
        $source = $request["source"] ?? ""; //小视频链接
        $isTransCode = $request["transcode"] ?? 0; //是否需要转码

        if (empty($source) || empty($cover)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new VideoService();
        $service->post($cover, $source, $isTransCode, $user);
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

        $service = new VideoService();
        $service->delete($id, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**********************************************个人动态接口结束****************************************************/
}