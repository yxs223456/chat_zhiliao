<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/18
 * Time: 17:44
 */

namespace app\common;

class AppException extends \Exception
{
    const QUERY_PARAMS_ERROR = [1000, "非法请求", "非法請求"];
    const QUERY_INVALID = [1001, "非法请求", "非法請求"];
    const TRY_AGAIN_LATER = [1002, "稍后重试", "稍後重試"];
    const QUERY_API_ERROR = [1003, "错误调用", "錯誤調用"];

    const USER_NOT_LOGIN = [2000, "请先登录", "請先登錄"];
    const USER_MOBILE_ERR = [2001, "请输入正确的手机号", "請輸入真確的手機號"];
    const USER_SEND_SMS_LIMIT = [2002, "短信触发小时级流控", "短信觸發小時級流控"];
    const USER_SEND_SMS_ERR = [2003, "短信发送失败", "短信發送失敗"];
    const USER_VERIFY_CODE_ERR = [2004, "验证码错误", "驗證碼錯誤"];
    const USER_USER_NUMBER_NOT_EXISTS = [2005, "用户编号不存在", "用戶編號不存在"];
    const USER_SEX_UNKNOWN = [2006, "请选择性别", "請選擇性別"];
    const USER_MODIFY_SEX_FORBIDDEN = [2007, "性别无法修改", "性別無法修改"];
    const USER_NOT_EXISTS = [2008, "用户不存在", "用戶不存在"];
    const USER_NOT_VIP = [2009, "您不是VIP", "您不是VIP"];
    const USER_IN_BLACK_LIST = [2010, "您已被对方添加到黑名单", "您已被對方添加到黑名單"];
    const USER_ACCOUNT_ERROR = [2011, "账号或密码错误", "賬號或密碼錯誤"];
    const USER_CERTIFICATE_SUBMIT_ALREADY = [2012, "自拍认证已提交", "自拍認證已提交"];
    const USER_CERTIFICATE_SUCCESS_ALREADY = [2013, "自拍认证已通过", "自拍認證已通過"];

    const DYNAMIC_CONTENT_EMPTY = [3001, "动态不能为空", "動態不能為空"];
    const DYNAMIC_NOT_EXISTS = [3002, "动态不存在", "動態不存在"];

    const VIP_OFFLINE = [4000, "该VIP套餐已下架", "該VIP套餐已下架"];

    const GIFT_OFFLINE = [5000, "该礼物已经下架", "該禮物已經下架"];
    const GIFT_RED_PACKAGE_AMOUNT_LESS = [5001, "红包金额不能小于10", "紅包金額不能小於10"];

    const WALLET_MONEY_LESS = [6000, "余额不足", "餘額不足"];

    const CHAT_VIDEO_CLOSE = [7000, "对方关闭视频接听", "對方關閉視頻接聽"];
    const CHAT_VOICE_CLOSE = [7001, "对方关闭语言接听", "對方關閉語音接聽"];
    const CHAT_LESS_MONTY = [7002, "余额不足无法发起聊天", "餘額不足無法發起聊天"];
    const CHAT_USER_CHAT_ING = [7003, "您正处于通话中，无法发起新聊天", "您正處於通話中，無法發起新聊天"];
    const CHAT_LINE_BUSY = [7004, "对方正处于通话中", "對法正處於通話中"];
    const CHAT_HANG_UP_CALLING = [7005, "通话已接通无法拒接", "通話已接通無法拒接"];
    const CHAT_END_ALREADY = [7006, "通话已结束", "通話已結束"];
    const CHAT_NOT_CALLING = [7007, "不在通话中", "不在通話中"];
    const CHAT_NOT_WAIT_ANSWER = [7008, "通话状态不为待接听", "通話狀態不為待接聽"];

    const VIDEO_NOT_EXISTS = [8001, "视频不存在", "視頻不存在"];

    public static function factory($errConst, $message = "", $lang = null)
    {
        $code = $errConst[0];
        $message = $message ? $message : self::getMessageByLang($errConst, $lang);
        $e = new self($message, $code);
        return $e;
    }

    protected static function getMessageByLang($errConst, $lang)
    {
        if ($lang == null) {
            $lang = getLanguage();
        }

        switch ($lang) {
            case "zh-tw":
                $message = $errConst[2];
                break;
            default:
                $message = $errConst[1];
                break;
        }
        return $message;
    }
}