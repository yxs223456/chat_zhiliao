<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\DynamicReportTypeEnum;
use app\common\enum\UserReportTypeEnum;
use app\common\enum\VideoReportTypeEnum;
use app\common\service\ReportService;

class Report extends Base
{
    /**
     * 用户发送验证码
     */
    public function getType()
    {
        $request = $this->query["content"];
        $type = $request["type"] ?? 0;

        if (empty($type)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!in_array($type, [ReportService::TYPE_DYNAMIC, ReportService::TYPE_USER, ReportService::TYPE_VIDEO])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new ReportService();
        $data = $us->getType($type);
        return $this->jsonResponse($data);
    }

    /**
     * 投诉
     */
    public function post()
    {
        $request = $this->query["content"];
        $type = $request["type"] ?? 0;
        $typeValue = $request["value"] ?? 0;
        $id = $request["id"] ?? 0;
        $content = $request["content"] ?? "";
        $source = $request["source"] ?? "";

        if (!in_array($type, [ReportService::TYPE_USER, ReportService::TYPE_DYNAMIC, ReportService::TYPE_VIDEO])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($type == ReportService::TYPE_USER) { // 用户投诉
            if (!in_array($typeValue, UserReportTypeEnum::getAllValues())) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
        }
        if ($type == ReportService::TYPE_DYNAMIC) { // 动态投诉
            if (!in_array($typeValue, DynamicReportTypeEnum::getAllValues())) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
        }
        if ($type == ReportService::TYPE_VIDEO) { // 视频投诉
            if (!in_array($typeValue, VideoReportTypeEnum::getAllValues())) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
        }
        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!is_string($source)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $source = array_filter(explode(",", $source));
        if (empty($content) || empty($source)) {
            throw AppException::factory(AppException::REPORT_CONTENT_NOT_EMPTY);
        }

        $userId = $this->query["user"]["id"];
        $service = new ReportService();
        $service->post($type, $typeValue, $content, $source, $id, $userId);
        return $this->jsonResponse(new \stdClass());
    }

}
