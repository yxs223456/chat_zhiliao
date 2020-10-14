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
use app\common\service\VideoService;
use app\v1\transformer\video\CityListTransformer;

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
        $isFlush = $request["is_flush"] ?? 0;
        $city = $request["city"] ?? "";
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($startId) || empty($city)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new VideoService();
        list($video, $likeVideoUserIds) = $service->cityList($startId, $pageSize, $city, $isFlush);
        return $this->jsonResponse($video, new CityListTransformer(
            ['user' => $user, 'likeVideoUserIds' => $likeVideoUserIds]
        ));
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
        $service = new DynamicService();
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
        $source = $request["source"] ?? ""; //小视频链接

        if (empty($source)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new VideoService();
        $service->post($source, $user);
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