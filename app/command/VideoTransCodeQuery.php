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
    private $videoId = "";

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
                "video_id" => $this->videoId,
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
        // 判断是否需要转码
        $video = Db::name("video")
            ->where("id", $this->videoId)
            ->field("u_id,source")
            ->where("is_transcode", VideoIsTransCodeEnum::NO)
            ->find();
        if (empty($video)) {// 视频不存在不需要转码
            return;
        }

        // 已添加转码任务直接返回
        $transcode = Db::name('video_transcode')->where("video_id", $this->videoId)->field("id")->find();
        if (!empty($transcode)) {
            return;
        }

        $bool = $this->needTransCode($video["source"]);
        if (!$bool) { // 是mp4不需要转码直接修改小视频转码状态
            Db::name("video")->where("id", $this->videoId)->update(["is_transcode" => VideoIsTransCodeEnum::YES]);
            return;
        }

        $source = $video["source"];
        $originName = AliyunOss::getObjectName($source);
        $toName = AliyunOss::objectNameToMp4($originName);
        $afterSource = str_replace($originName, $toName, $source);
        // 发送转码请求
        $request = AliyunOss::mtsRequest($originName, $toName);
        if (empty($request)) {
            throw new Exception("videoId : $this->videoId transcode request error");
        }

        $insert = [
            'video_id' => $this->videoId,
            'source' => $source,
            'after_source' => $afterSource,
            'request_id' => $request["RequestId"] ?? "",
            'job_id' => $request["JobResultList"]["JobResult"][0]["Job"]["JobId"] ?? "",
            'request_result' => json_encode($request)
        ];

        return Db::name("video_transcode")->insert($insert);
    }
}