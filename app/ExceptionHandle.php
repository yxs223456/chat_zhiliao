<?php
namespace app;

use app\common\AppException;
use app\common\helper\WeChatWork;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Log;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制
        if ($e instanceof AppException) {
            return json([
                "code" => $e->getCode(),
                "msg" => $e->getMessage(),
            ]);
        } else if (config("app.app_environment") == 1) {
            try {
                $log = [
                    "message" => $e->getMessage(),
                    "content" => $request->getContent(),
                    "header" => $request->header(),
                    "method" => $request->method(),
                    "input" => $request->getInput(),
                    "api" => $request->server("REDIRECT_URL"),
                    "ip" => $request->ip(),
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "code" => $e->getCode(),
                ];
                Log::write(json_encode($log), "error");
                $weChatWorkString = "";
                foreach ($log as $key=>$value) {
                    if ($key == "header") {
                        $valueStr = "";
                        foreach ($value as $headerKey => $headerValue) {
                            $valueStr .= "\n    " . $headerKey . ":$headerValue";
                        }
                        $value = $valueStr;
                    } elseif (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $weChatWorkString .= $key . ":" . $value . "\n";
                }
                $weChatWorkString = substr($weChatWorkString, 0 , -1);
                WeChatWork::sendMessageToUser($weChatWorkString);
            } catch (Throwable $newE) {
            }
            if ($e instanceof HttpException) {
                return json([
                    "code" => 404,
                ]);
            } else {
                return json([
                    "code" => "-1",
                    "msg" => "网络请求异常，请稍后再试",
                ]);
            }
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
