<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-10
 * Time: 11:13
 */

namespace app\gateway;

use app\common\transformer\TransformerAbstract;
use GatewayClient\Gateway;

class GatewayClient
{
    private static function init()
    {
        Gateway::$registerAddress = config("gateway_worker.registerAddress");
    }

    public static function sendToUid($userId, $scene, $data, TransformerAbstract $transformer = null, $msg = "ok")
    {
        self::init();
        $message = BusinessWorkerCallback::jsonData($scene, $data, $transformer, $msg);
        Gateway::sendToUid($userId, $message);
    }
}