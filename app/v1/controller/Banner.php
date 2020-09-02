<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-02
 * Time: 10:03
 */

namespace app\v1\controller;

use app\common\service\BannerService;
use app\v1\transformer\banner\Home;

class Banner extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "only" => "",
        ]
    ];

    /**
     * 首页banner
     */
    public function home()
    {
        $service = new BannerService();
        $returnData = $service->home();

        return $this->jsonResponse($returnData, new Home());
    }
}
