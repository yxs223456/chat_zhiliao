<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-28
 * Time: 15:14
 */

namespace app\v1\controller;

use app\BaseController;

class PayCallback extends BaseController
{
    /**
     * 微信购买vip回调
     */
    public function weChatVip()
    {
        $request = $this->request->getContent();
        $requestArray = json_decode($request, true);
        if (!$requestArray || !is_array($requestArray) || empty($requestArray["appKey"])) {
            return;
        }

    }

    private function weChatCommon()
    {

    }
}