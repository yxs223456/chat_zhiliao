<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-04
 * Time: 14:03
 */

namespace app\gateway;

use app\common\helper\Redis;
use app\common\service\UserService;
use app\common\transformer\TransformerAbstract;
use GatewayWorker\Lib\Gateway;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;
use think\facade\Db;
use think\worker\Application;
use Workerman\Worker;

/**
 * Worker 命令行服务类
 */
class BusinessWorkerCallback
{
    /**
     * onWorkerStart 事件回调
     * 当businessWorker进程启动时触发。每个进程生命周期内都只会触发一次
     *
     * @access public
     * @param  \Workerman\Worker    $businessWorker
     * @return void
     */
    public static function onWorkerStart(Worker $businessWorker)
    {
        $app = new Application();
        $app->initialize();
    }

    /**
     * onConnect 事件回调
     * 当客户端连接上gateway进程时(TCP三次握手完毕时)触发
     *
     * @access public
     * @param  int       $client_id
     * @return void
     */
    public static function onConnect($client_id)
    {
        try {
            $scene = "init_connect";
            $returnData = [
                "client_id" => $client_id
            ];
            $responseJson = self::jsonData($scene, $returnData);
            Gateway::sendToClient($client_id, $responseJson);
        } catch (\Throwable $e) {

        }

    }

    /**
     * onWebSocketConnect 事件回调
     * 当客户端连接上gateway完成websocket握手时触发
     *
     * @param  integer  $client_id 断开连接的客户端client_id
     * @param  mixed    $data
     * @return void
     */
    public static function onWebSocketConnect($client_id, $data)
    {
        try {
            if (isset($data["get"]["v"])) {
                $_SESSION["v"] = $data["get"]["v"];
            }
            if (isset($data["get"]["os"])) {
                $_SESSION["os"] = $data["get"]["os"];
            }
            if (isset($data["get"]["token"])) {
                $user = UserService::getUserByToken($data["get"]["token"]);
                if ($user) {
                    // 断开旧连接，并发送异地登录消息
                    $oldClientIdArr = Gateway::getClientIdByUid($user["id"]);
                    foreach ($oldClientIdArr as $oldClientId) {
                        $scene = "another_site_login";
                        $returnData = [];
                        $responseJson = self::jsonData($scene, $returnData);
                        Gateway::sendToClient($oldClientId, $responseJson);
                        Gateway::closeClient($oldClientId);
                    }

                    // uid与当前client_id 绑定
                    $_SESSION["user"] = $user;
                    Gateway::bindUid($client_id, $user["id"]);
                    $scene = "init_user";
                    $returnData = [
                        "id" => $user["id"],
                    ];
                    $responseJson = self::jsonData($scene, $returnData);
                    Gateway::sendToClient($client_id, $responseJson);

                    loginAndLogoutCallbackProduce($user["id"], "login", Redis::factory());
                }
            }
        } catch (\Throwable $e) {

        }

    }

    /**
     * onMessage 事件回调
     * 当客户端发来数据(Gateway进程收到数据)后触发
     *
     * @access public
     * @param  int       $client_id
     * @param  mixed     $data
     * @return void
     */
    public static function onMessage($client_id, $data)
    {
        try {

        } catch (\Throwable $e) {

        }
    }

    /**
     * onClose 事件回调 当用户断开连接时触发的方法
     *
     * @param  integer $client_id 断开连接的客户端client_id
     * @return void
     * @throws \Exception
     */
    public static function onClose($client_id)
    {
        if (isset($_SESSION["user"])) {
            loginAndLogoutCallbackProduce($_SESSION["user"]["id"], "logout", Redis::factory());
        }
    }

    /**
     * onWorkerStop 事件回调
     * 当businessWorker进程退出时触发。每个进程生命周期内都只会触发一次。
     *
     * @param  \Workerman\Worker    $businessWorker
     * @return void
     */
    public static function onWorkerStop(Worker $businessWorker)
    {
//        echo "WorkerStop\n";
    }

    public static function jsonData($scene, array $data = [], TransformerAbstract $transformer = null, $msg = "ok")
    {
        if ($transformer !== null) {
            $fractal = new Manager();
            $fractal->setSerializer(new ArraySerializer());
            // 关联数组(一维数组)还是索引数组(二维数组), 需要依此返回数据
            // 如果是关联数组则默认为一维数组的转化逻辑
            if (array_keys($data) !== array_keys(array_keys($data))) {
                $resource = new Item($data, $transformer);
                return $fractal->createData($resource)->toArray();
            }
            // 如果是索引数组则默认为二维数组的转化逻辑
            $resource = new Collection($data, $transformer);
            $data = $fractal->createData($resource)->toArray()['data'];
        } else if ($data === []) {
            $data = new \stdClass();
        }
        $rs = [
            'code' => 0,
            'msg' => $msg,
            "scene" => $scene,
            'data' => $data,
        ];
        return json_encode($rs);
    }
}