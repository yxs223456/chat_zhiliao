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
        $data = AliyunOss::getToken();
        $config = config("account.oss");
        $data["ObjectKey"] = $config["key_id"];
        $data["BucketName"] = $config["bucket"];
        $data["EndPoint"] = $config["endpoint"];
        return $data;
    }
}