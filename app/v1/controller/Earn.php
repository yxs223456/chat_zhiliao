<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/24
 * Time: 下午1:49
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\EarnService;

class Earn extends Base
{

    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**************************************************（男收入总榜）***************************************************/

    /**
     * 周榜
     */
    public function weekRank()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new EarnService();
        $data = $service->weekRank($pageNum, $pageSize, $user);
        return $this->jsonResponse($data);
    }

    /**
     * 总榜
     */
    public function rank()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new EarnService();
        $data = $service->rank($pageNum, $pageSize, $user);
        return $this->jsonResponse($data);
    }
}