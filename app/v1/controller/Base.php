<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/18
 * Time: 16:24
 */

namespace app\v1\controller;

use app\BaseController;
use app\common\AppException;
use app\common\enum\UserSexEnum;
use app\common\helper\Redis;
use app\common\model\UserModel;
use app\common\transformer\TransformerAbstract;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;

class Base extends BaseController
{
    public $query = [
        'content' => [],
        'user' => [],
        "v" => "",
        "os" => "",
    ];

    protected $beforeActionList = [
        "getUser" => [
            "only" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    protected function initialize()
    {
        header("Access-Control-Allow-Origin:*");
        header('Access-Control-Allow-Credentials: true');
        header("Access-Control-Allow-Headers:token");
        $this->query["v"] = $this->request->header('v');
        $this->query["os"] = $this->request->header('os');

        $content = $this->request->getContent();
        if ($content) {
            $this->query["content"] = json_decode($content, true);
        }

        parent::initialize();
        static::checkToken();

        foreach ((array)$this->beforeActionList as $method => $options) {
            is_numeric($method) ?
            $this->beforeAction($options) :
            $this->beforeAction($method, $options);
        }
    }

    protected function jsonResponse($data, TransformerAbstract $transformer = null, $msg = "success")
    {
        if ($transformer !== null) {
            $data = $this->transformData($data, $transformer);
        }

        $rs = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ];
        return json($rs);
    }

    protected function checkToken()
    {
        $token = $this->request->header('token');
        if (empty($token)) {
            $isLogin = false;
            $user = [];
        } else {
            $redis = Redis::factory();
            $cacheUser = getUserInfoByToken($token, $redis);
            if (empty($cacheUser['id'])) {
                $model = new UserModel();
                $userModel = $model->findByToken($token);
                if (!$userModel) {
                    $isLogin = false;
                    $user = [];
                } else {
                    $redis = Redis::factory();
                    $user = $userModel->toArray();
                    $isLogin = true;
                    cacheUserInfoByToken($user, $redis);
                }
            } else {
                $isLogin = true;
                $user = $cacheUser;
            }
        }
        $GLOBALS['isLogin'] = $isLogin;
        $this->query["user"] = $user;
    }

    protected function getUser()
    {
        if (!$GLOBALS['isLogin'] || !isset($this->query["user"]["sex"])) {
            throw AppException::factory(AppException::USER_NOT_LOGIN);
        }
    }

    protected function checkSex()
    {
        if (isset($this->query["user"]["sex"]) && $this->query["user"]["sex"] == UserSexEnum::UNKNOWN) {
            throw AppException::factory(AppException::USER_SEX_UNKNOWN);
        }
    }

    /**
     * 前置操作
     * @access protected
     * @param  string $method  前置操作方法名
     * @param  array  $options 调用参数 ['only'=>[...]] 或者['except'=>[...]]
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = strtolower($options['only']);
                $options['only'] = explode(',', $options['only']);
            }
            foreach ($options['only'] as &$only) {
                $only = trim($only);
            }
            unset($only);
            if (!in_array($this->request->action(true), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = strtolower($options['except']);
                $options['except'] = explode(',', $options['except']);
            }
            foreach ($options['except'] as &$except) {
                $except = trim($except);
            }
            unset($except);
            if (in_array($this->request->action(true), $options['except'])) {
                return;
            }
        }
        call_user_func([$this, $method]);
    }

    /**
     * 自定义参数转化器.
     *
     * @param array                $data
     * @param TransformerAbstract $transformer
     *
     * @return array
     */
    private function transformData(array $data, TransformerAbstract $transformer): array
    {
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
        return $fractal->createData($resource)->toArray()['data'];
    }
}