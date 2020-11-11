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
use app\common\helper\RabbitMQ;
use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use PhpAmqpLib\Message\AMQPMessage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

/**
 * 视频转码请求
 */
class VideoTransCode extends Command
{
    use CommandTrait;

    const MP4 = "video/mp4";

    private $beginTime;
    private $videoId = "";

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:VideoTransCode');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->beginTime = time();
//            videoTransCodeConsumer([$this, 'receive']);

            $redis = Redis::factory();
            while (time() - $this->beginTime <= $this->maxAllowTime) {
                $data = videoTransCodeConsumer($redis);
                Log::write(json_encode($data),"error");
                if (!empty($data["videoId"])) {
                    $this->videoId = $data["videoId"];
                    $this->doWorkR();
                }
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

    public function receive(AMQPMessage $message)
    {
        //消息内容
        $msg = $message->getBody();

        //确保当前脚本不占用太多内存
        $usedMemory = memory_get_usage();
        if ($usedMemory >= $this->maxAllowMemory ||
            time() - $this->beginTime >= $this->maxAllowTime)
        {
            //拒绝消息，并把消息重新放回队列
            RabbitMQ::rejectMessage($message);
            exit;
        }

        //判断数据是否合法
        $msgArray = json_decode($msg, true);
        if (!is_array($msgArray) || empty($msgArray["videoId"])) {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
            return;
        }
        $this->videoId = $msgArray["videoId"];
        // 判断是访问和被访问是否是同一个人，同一个人忽略
        $this->doWork($message);
    }

    private function doWork(AMQPMessage $message)
    {
        // 判断是否需要转码
        $video = Db::name("video")
            ->where("id", $this->videoId)
            ->field("u_id,source")
            ->where("transcode_status", VideoIsTransCodeEnum::TRANSCODING)
            ->find();
        if (empty($video)) {// 视频不存在不需要转码
            RabbitMQ::ackMessage($message);
            return;
        }
        $bool = $this->needTransCode($video["source"]);
        // 不需要转码更新转码状态
        if (!$bool) {
            RabbitMQ::ackMessage($message);
            Db::name("video")->where("id", $this->videoId)->update(["transcode_status" => VideoIsTransCodeEnum::SUCCESS]);
            return;
        }

        Db::startTrans();
        try {


            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        } finally {
            //显示确认，队列接收到显示确认后会删除该消息
            RabbitMQ::ackMessage($message);
        }

    }

    /**
     * redis 队列处理逻辑
     *
     * @throws \Throwable
     */
    private function doWorkR()
    {
        // 判断是否需要转码
        $video = Db::name("video")
            ->where("id", $this->videoId)
            ->field("u_id,source")
            ->where("transcode_status", VideoIsTransCodeEnum::TRANSCODING)
            ->find();
        if (empty($video)) {// 视频不存在不需要转码
            Log::write($this->videoId . "视频不存在\n");
            return;
        }

        // 已添加转码任务直接返回
        $transcode = Db::name('video_transcode')->where("video_id", $this->videoId)->field("id")->find();
        if (!empty($transcode)) {
            Log::write($this->videoId . "任务已添加\n");
            return;
        }

        $source = $video["source"];
        $originName = AliyunOss::getObjectName($source);
        $toName = AliyunOss::objectNameToMp4($originName);
        $afterSource = str_replace($originName, $toName, $source);

        // 发送转码请求
        $result = AliyunOss::mtsRequest($originName, $toName);
        $body = $result->getBody();
        $content = json_decode($body);
        // 提交失败
        if (!$result->isSuccess() || empty($content->JobResultList->JobResult[0]->Success)) {
            Db::name("video")->where("id", $this->videoId)->update(["transcode_status" => VideoIsTransCodeEnum::ERROR]);
            throw new Exception("videoId : $this->videoId transcode request error");
            return;
        }

        $insert = [
            'video_id' => $this->videoId,
            'source' => $source,
            'after_source' => $afterSource,
            'request_id' => $content->RequestId ?? "",
            'job_id' => $content->JobResultList->JobResult[0]->Job->JobId ?? "",
            'request_result' => json_encode($content)
        ];

        return Db::name("video_transcode")->insert($insert);
    }
}