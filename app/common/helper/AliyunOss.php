<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/18
 * Time: 10:29
 */
namespace app\common\helper;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use OSS\Core\OssException;
use OSS\OssClient;
use think\facade\Log;

class AliyunOss
{
    protected static $accessKeyId = "";
    protected static $accessKeySecret = "";
    protected static $endpoint = "";
    protected static $bucket = "";
    protected static $roleArn = "";
    protected static $tokenExpireTime = 900;
    protected static $regionId = "cn-beijing";
    protected static $pipelineId = "";
    protected static $templateId= "";
    protected static $ossLocation= "oss-cn-beijing";

    private static function getConfig()
    {
        $config = config("account.oss");
        self::$accessKeyId = $config["key_id"];
        self::$accessKeySecret = $config["key_secret"];
        self::$endpoint = $config["endpoint"];
        self::$bucket = $config["bucket"];
        self::$roleArn = $config["role_arn"];
        self::$tokenExpireTime = $config["token_expire_time"];
        self::$regionId = $config["region_id"];
        self::$pipelineId = $config["pipeline_id"];
        self::$templateId = $config["template_id"];
        self::$ossLocation = $config["oss_location"];
    }

    /**
     * @param $filename string 文件名称
     * @param string $content The content object
     * @return string
     * @throws \OSS\Core\OssException
     */
    public static function putObject($filename, $content)
    {
        self::getConfig();

        $ossClient = new OssClient(self::$accessKeyId, self::$accessKeySecret, self::$endpoint);
        $result = $ossClient->putObject(self::$bucket, $filename, $content);
        if (isset($result['info']['url']) && strpos($result['info']['url'], "https") === false) {
            $result['info']['url'] = str_replace("http", "https", $result['info']['url']);
            return $result['info']['url'];
        } else {
            return "";
        }
    }

    /**
     * 获取oss直传token
     *
     * @return array
     */
    public static function getToken()
    {
        // 加载配置文件
        self::getConfig();
        AlibabaCloud::accessKeyClient(self::$accessKeyId, self::$accessKeySecret)
            ->regionId(self::$regionId)
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Sts')
                 ->scheme('https') // https | http
                ->version('2015-04-01')
                ->action('AssumeRole')
                ->method('POST')
                ->options([
                    'query' => [
                        'RegionId' => self::$regionId,
                        'RoleArn' => self::$roleArn,
                        'RoleSessionName' => "chat_zhiliao",
                        'DurationSeconds' => self::$tokenExpireTime
                    ],
                ])
                ->request();

            $rows = array();
            $body = $result->getBody();
            $content = json_decode($body);

            if ($result->isSuccess()) {
                $rows['StatusCode'] = 200;
                $rows['AccessKeyId'] = $content->Credentials->AccessKeyId;
                $rows['AccessKeySecret'] = $content->Credentials->AccessKeySecret;
                $rows['Expiration'] = $content->Credentials->Expiration;
                $rows['SecurityToken'] = $content->Credentials->SecurityToken;
            } else {
                $rows['StatusCode'] = 500;
                $rows['ErrorCode'] = $content->Code;
                $rows['ErrorMessage'] = $content->Message;
            }
            return $rows;
        } catch (ClientException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        } catch (ServerException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        }
    }

    /**
     *  提交转码作业请求
     *
     * @param $objectName
     * @param $toName
     * @return \AlibabaCloud\Client\Result\Result
     */
    public static function mtsRequest($objectName, $toName)
    {
        // 加载配置文件
        self::getConfig();
        AlibabaCloud::accessKeyClient(self::$accessKeyId, self::$accessKeySecret)
            ->regionId(self::$regionId)
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Mts')
                ->scheme('https')// https | http
                ->version('2014-06-18')
                ->action('SubmitJobs')
                ->method('POST')
                ->options([
                    'query' => [
                        'Input' => json_encode([
                            'Location' => self::$ossLocation,
                            'Bucket' => self::$bucket,
                            "Object" => urlencode($objectName)
                        ]),
                        'Outputs' => json_encode([
                            [
                                'OutputObject' => urlencode($toName),
                                'TemplateId' => self::$templateId
                            ]
                        ]),
                        'OutputBucket' => self::$bucket,
                        'OutputLocation' => self::$ossLocation,
                        'PipelineId' => self::$pipelineId,
                    ]
                ])
                ->request();

            return $result;
        } catch (ClientException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        } catch (ServerException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        }
    }

    /**
     *  查询转码作业结果
     *
     * @param $jobIds
     * @return \AlibabaCloud\Client\Result\Result
     */
    public static function mtsQuery($jobIds)
    {
        // 加载配置文件
        self::getConfig();
        AlibabaCloud::accessKeyClient(self::$accessKeyId, self::$accessKeySecret)
            ->regionId(self::$regionId)
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Mts')
                ->scheme('https')// https | http
                ->version('2014-06-18')
                ->action('QueryJobList')
                ->method('POST')
                ->options([
                    'query' => [
                        'JobIds' => $jobIds,
                    ]
                ])
                ->request();

            return $result;
        } catch (ClientException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        } catch (ServerException $e) {
            Log::error($e->getErrorMessage() . PHP_EOL);
        }
    }

    /**
     * 删除oss文件
     *
     * @param $object string 文件名称
     * @return string
     * @throws \OSS\Core\OssException
     */
    public static function deleteObject($object)
    {
        self::getConfig();
        try {
            $ossClient = new OssClient(self::$accessKeyId, self::$accessKeySecret, self::$endpoint);
            $ossClient->deleteObject(self::$bucket, $object);
        } catch (OssException $e) {
            throw $e;
        }
        return true;
    }

    /**
     * 获取objectName
     *
     * @param $object
     * @return string
     */
    public static function getObjectName($object)
    {
        self::getConfig();
        $objectName = explode(self::$endpoint, $object);
        return trim(array_pop($objectName), DIRECTORY_SEPARATOR);
    }

    /**
     * 文件名称修改方法
     *
     * @param $object
     * @return string
     */
    public static function objectNameToMp4($object)
    {
        if (strpos($object, ".") === false) {
            return $object . ".mp4";
        }
        return substr($object, 0, strpos($object, ".")) . ".mp4";
    }
}