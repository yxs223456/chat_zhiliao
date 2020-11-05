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
        return AliyunOss::getToken();
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
        $fileName = "1602656808842121.mp4";
        return AliyunOss::mtsRequest($fileName);
    }
}