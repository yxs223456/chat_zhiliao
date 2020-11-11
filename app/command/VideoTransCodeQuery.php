<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-01
 * Time: 16:29
 */
namespace app\command;

use app\common\enum\VideoIsTransCodeEnum;
use app\common\helper\AliyunOss;
use app\common\helper\WeChatWork;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

/**
 * 视频转码查询
 */
class VideoTransCodeQuery extends Command
{
    use CommandTrait;

    const MP4 = "video/mp4";

    private $beginTime;
    private $jobId;
    private $jobIdToData;

    const SUBMITTED = "Submitted";
    const TRANSCODING = "Transcoding";
    const TRANSCODE_SUCCESS = "TranscodeSuccess";
    const TRANSCODE_FAIL = "TranscodeFail";
    const TRANSCODE_CANCELLED = "TranscodeCancelled";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:VideoTransCodeQuery');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();

            while (time() - $this->beginTime <= $this->maxAllowTime) {
                $this->doWork();
            }
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "job_id" => $this->jobId,
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "message" => $e->getMessage(),
            ];
            Log::write(json_encode($error), "error");
            $errorMessage = "";
            foreach ($error as $key=>$value) {
                $errorMessage .= "$key: " . $value . "\n";
            }
            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yanglichao"]);
        }
    }

    /**
     * 查询数据库然后调用接口查询
     *
     * @throws \Throwable
     */
    private function doWork()
    {
        // 获取转码中的视频
        $transcode = Db::name("video_transcode")
            ->where("status", VideoIsTransCodeEnum::TRANSCODING)
            ->field("id,video_id,after_source,status,request_id,job_id")
            ->limit(1)
            ->select()->toArray();
        if (empty($transcode)) {// 不存在转码中的视频
            // 防止程序过快退出
            sleep(10);
            return;
        }
        // 把处理的数据放入全局
        $this->jobIdToData = array_combine(array_column($transcode, "job_id"), $transcode);
        $objectIdArr = array_column($transcode, "job_id");
        $objectIds = implode(",", $objectIdArr);

        // 发送转码查询请求
        $result = AliyunOss::mtsQuery($objectIds);
        if (!$result->isSuccess()) {
            throw new Exception("transcode jobIds : $objectIds transcode result request error");
        }

        $queryResult = $result->JobList->Job ?? [];
        foreach ($queryResult as $item) {
            $this->updateDb($item);
        }
    }

    private function updateDb($jobResult)
    {
        $jobId = $jobResult->JobId;
        $state = $jobResult->State;
        $video = $this->jobIdToData[$jobId] ?? [];
        if (empty($jobId) || empty($state) || empty($video)) {
            throw new Exception("jobId:$jobId, state:$state or video empty");
        }
        // 提交成功和转码中不处理
        if (in_array($state, [self::SUBMITTED, self::TRANSCODING])) {
            return;
        }
        Db::startTrans();
        try {
            $update = ["query_result" => json_encode($jobResult)];
            $videoUpdate = [];
            // 更新转码状态为成功
            if ($state == self::TRANSCODE_SUCCESS) {
                $update["status"] = VideoIsTransCodeEnum::SUCCESS;

                $videoUpdate["source"] = $video["after_source"] ?? "";
                $videoUpdate["transcode_status"] = VideoIsTransCodeEnum::SUCCESS;
            } else if ($state == self::TRANSCODE_FAIL) {
                $update["status"] = VideoIsTransCodeEnum::ERROR;
                $videoUpdate["transcode_status"] = VideoIsTransCodeEnum::ERROR;
            } else if ($state == self::TRANSCODE_CANCELLED) {
                $update["status"] = VideoIsTransCodeEnum::CANCEL;
                $videoUpdate["transcode_status"] = VideoIsTransCodeEnum::SUCCESS;
            }
            // 更新转码请求表
            Db::name("video_transcode")->where("job_id", $jobId)->update($update);
            // 更新视频表
            Db::name("video")->where("id", $video["video_id"])->update($videoUpdate);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}