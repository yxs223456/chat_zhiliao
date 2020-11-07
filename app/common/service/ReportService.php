<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


use app\common\AppException;
use app\common\enum\DynamicReportTypeEnum;
use app\common\enum\UserReportTypeEnum;
use app\common\enum\VideoReportTypeEnum;
use think\facade\Db;

class ReportService extends Base
{
    const TYPE_USER = 1;
    const TYPE_DYNAMIC = 2;
    const TYPE_VIDEO = 3;

    /**
     *  获取投诉类型
     *
     * @param $type
     * @return array
     * @throws AppException
     */
    public function getType($type)
    {
        $lang = getLanguage();
        switch ($type) {
            case self::TYPE_USER:
                if ($lang == "zh-tw") {
                    return UserReportTypeEnum::getAllList("tw");
                }
                return UserReportTypeEnum::getAllList();
            case self::TYPE_DYNAMIC:
                if ($lang == "zh-tw") {
                    return DynamicReportTypeEnum::getAllList("tw");
                }
                return DynamicReportTypeEnum::getAllList();
            case self::TYPE_VIDEO:
                if ($lang == "zh-tw") {
                    return VideoReportTypeEnum::getAllList("tw");
                }
                return VideoReportTypeEnum::getAllList();
        }
        return [];
    }

    /**
     * 提交投诉
     *
     * @param $type int 投诉种类1-人，2-动态，3-视频
     * @param $typeValue int 投诉类型
     * @param $content string 投诉内容
     * @param $source array 投诉图片
     * @param $sourceId int 投诉的ID
     * @param $userId int 提交投诉的用户ID
     * @return bool
     * @throws AppException
     */
    public function post($type, $typeValue, $content, $source, $sourceId, $userId)
    {
        $insertData = [
            "u_id" => $userId,
            "type" => $typeValue,
            "content" => $content,
            "source" => json_encode($source)
        ];
        switch ($type) {
            case self::TYPE_USER:
                $user = UserService::getUserById($sourceId);
                if (empty($user)) {
                    throw AppException::factory(AppException::USER_NOT_EXISTS);
                }
                $insertData["report_u_id"] = $sourceId;
                Db::name("user_report")->insert($insertData);
                break;
            case self::TYPE_DYNAMIC:
                $dynamic = Db::name("dynamic")->field("id")->where("id", $sourceId)->find();
                if (empty($dynamic)) {
                    throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
                }
                $insertData["dynamic_id"] = $sourceId;
                Db::name("dynamic_report")->insert($insertData);
                break;
            case self::TYPE_VIDEO:
                $video = Db::name("video")->field("id")->where("id", $sourceId)->find();
                if (empty($video)) {
                    throw AppException::factory(AppException::VIDEO_NOT_EXISTS);
                }
                $insertData["video_id"] = $sourceId;
                Db::name("video_report")->insert($insertData);
                break;
        }
        return true;
    }
}