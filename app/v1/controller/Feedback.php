<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\FeedbackService;

class Feedback extends Base
{
    /**
     * 发布意见反馈
     */
    public function post()
    {
        $request = $this->query["content"];
        $msg = $request["content"] ?? "";
        $user = $this->query["user"];
        if (empty($msg)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (strlen($msg) > 1000) {
            throw AppException::factory(AppException::CONTENT_TOO_LONG);
        }

        $service = new FeedbackService();
        $service->post($user, $msg);
        return $this->jsonResponse(new \stdClass());
    }
}