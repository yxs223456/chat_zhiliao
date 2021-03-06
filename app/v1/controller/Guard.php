<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/24
 * Time: 下午1:49
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\GuardService;
use app\v1\transformer\guard\CurrentTransformer;
use app\v1\transformer\guard\RecentlyTransformer;
use app\v1\transformer\guard\WaitTransformer;

class Guard extends Base
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
     * 等待守护
     */
    public function wait()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 10;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new GuardService();
        $data = $service->wait($user, $pageNum, $pageSize);
        return $this->jsonResponse($data, new WaitTransformer());
    }

    /**
     * 正在守护
     */
    public function current()
    {
        $user = $this->query['user'];

        $service = new GuardService();
        $data = $service->current($user);
        return $this->jsonResponse($data, new CurrentTransformer());
    }

    /**
     * 最近守护 (三个月)
     */
    public function recently()
    {
        $user = $this->query['user'];

        $service = new GuardService();
        $data = $service->recently($user);
        return $this->jsonResponse($data, new RecentlyTransformer());
    }

}