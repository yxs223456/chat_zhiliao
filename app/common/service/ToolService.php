<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:32
 */

namespace app\common\service;

use app\common\helper\AliyunOss;

class ToolService extends Base
{
    public function ossToken()
    {
        return AliyunOss::getToken1();
    }

    public function ossConfig()
    {
        $config = config("account.oss");
        $data = [];
        $data["bucket"] = $config["bucket"];
        $data["endpoint"] = $config['endpoint'];
        return $data;
    }

    public function mtsRequest()
    {
        return AliyunOss::mtsRequest();
    }
}