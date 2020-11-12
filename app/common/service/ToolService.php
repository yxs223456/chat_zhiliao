<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:32
 */

namespace app\common\service;

use app\common\helper\AliyunOss;
use app\common\helper\Redis;

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
        return AliyunOss::mtsRequest($fileName, "1602656808842121-test-test.mp4");
    }

    public function del()
    {
//        return AliyunOss::deleteObject("https://yanglc.oss-cn-beijing.aliyuncs.com/xxxxx");
        return AliyunOss::objectNameToMp4("asdfasdfadsf123.api.asdf");
    }

    public function push()
    {
        videoTransCodeProduce(58, Redis::factory());
        return ["code" => 200];
    }
}