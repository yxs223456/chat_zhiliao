<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-20
 * Time: 11:22
 */

namespace app\v1\controller;

use app\BaseController;

class RongCloudCallback extends BaseController
{
    /**
     * 音视频通话回调接口
     */
    public function rtcCallback()
    {
        $request = $this->request->getContent();
        $requestArray = json_decode($request, true);
        if (!$requestArray || !is_array($requestArray) || empty($requestArray["appKey"])) {
            return;
        }



    }
}