<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-07
 * Time: 11:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\ScoreService;

class Score extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**
     * 评分
     */
    public function appraises()
    {
        $request = $this->query["content"];
        $chatId = $request["chat_id"]??0;
        $score = $request["score"]?? 1;
        if (!checkInt($chatId, false) || $score > 5 || $score < 1) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new ScoreService();
        $service->appraises($user, $chatId, $score);
        return $this->jsonResponse(new \stdClass());
    }
}