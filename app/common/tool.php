<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/18
 * Time: 18:13
 */

function checkIsMobile($mobile)
{
    if (empty($mobile) || !is_numeric($mobile) || strlen($mobile) != 11) {
        return false;
    } else {
        return true;
    }
}

function checkIsPhone($phone)
{
    if (empty($phone) || !is_numeric($phone)) {
        return false;
    }
    return true;
}

/**
 * @param $url                      string 请求地址
 * @param $method                   string 请求类型
 * @param $postData                 null|string|array 请求的post数据
 * @param $isPostDataJsonEncode     bool post数据是否需要为json格式
 * @param $isResponseJson           bool 请求$url的响应是否为json
 * @param $cookie                   null|string cookie数据
 * @param $header                   null|array 请求的消息报头
 * @param $isReturnHeader           bool 是否返回响应头信息
 * @return mixed                    请求的响应数据，失败时返回false
 * @throws Exception
 */
function curl($url, $method = 'get', $postData = null, $isPostDataJsonEncode = false, $isResponseJson = false, $header = null, $cookie = null, $isReturnHeader = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (stripos($url, 'https') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if ($isReturnHeader) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
    }
    if (strtolower($method) == 'post') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($isPostDataJsonEncode && is_array($postData)) {
            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if (!empty($header) && is_array($header) && count($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    $data = curl_exec($ch);
    $err = curl_error($ch);
    if ($err) {
        $log = json_encode([
            "url" => $url,
            "data" => $postData,
            "header" => $header,
            "cookie" => $cookie,
            "reason" => $err,
        ], JSON_UNESCAPED_UNICODE);
        \think\facade\Log::write("curl exec error: $log", "error");
        throw new \Exception("curl field!");
    }
    if ($isResponseJson) {
        $data = json_decode($data, true);
    }
    return $data;
}

/**
 * 生成随机字符串
 */
function getRandomString($length = 32, $isNumeric = false) {
    if ($isNumeric) {
        $chars = '0123456789';
    } else {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    }
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}

/**
 * 检验参数是否在给定的几个值内
 * @param $param
 * @param array ...$ranges
 * @param bool ...$strict
 * @return bool
 */
function checkIsIn($param, $strict = true, ...$ranges)
{
    if ($strict) {
        if (in_array($param, $ranges, true)) {
            return true;
        }
    } else {
        if (in_array($param, $ranges)) {
            return true;
        }
    }
    return false;
}

/**
 * 验证是否为整数，默认验证 >=0 的整数
 * @param mixed $param 要验证的参数
 * @param bool $zero 是否准许为0
 * @param bool $positive 是否不小于0
 * @return bool
 */
function checkInt($param, $zero = true, $positive = true)
{
    if (!preg_match('#^-?\d+$#', $param)) {
        return false;
    }
    if (!$zero && $param == 0) {
        return false;
    }
    if ($positive && $param < 0) {
        return false;
    }
    return true;
}

/**
 * 验证日期格式是否正确
 * @param $datetime string 精确到秒的日期格式
 * @return bool
 */
function checkDatetime($datetime)
{
    if (!empty($datetime)) {
        $dateArr = explode(' ', $datetime);
        if (count($dateArr) != 2) {
            return false;
        }
        $limitDayArr = explode('-', $dateArr[0]);
        if (count($limitDayArr) != 3 || !checkdate($limitDayArr[1], $limitDayArr[2], $limitDayArr[0])) {
            return false;
        }
        if (!preg_match('/^([01]\d:[012345]\d:[012345]\d)$|^(2[0123]:[012345]\d:[012345]\d)$/', $dateArr[1])) {
            return false;
        }
        return true;
    }
    return false;
}

function hideUserPhone($phone)
{
    if (preg_match('#^\d{11}$#', $phone)) {
        return substr($phone, 0, 3) . '****' . substr($phone, 7);
    }
    return $phone;
}

function hideInternationalPhone($phone)
{
    if (preg_match('#^\d{7,}$#', $phone)) {
        return substr($phone, 0, 2) . str_pad("", strlen($phone)-6, "*") . substr($phone, strlen($phone) - 4);
    }
    return $phone;
}

// 验证交易密码 必须包含大写，小写字母和数字，不少于8位
function checkTradePass($tradePass)
{
    if (mb_strlen($tradePass) < 8) {
        return false;
    }
    if (!preg_match('/[a-z]/', $tradePass)) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $tradePass)) {
        return false;
    }
    if (!preg_match('/\d/', $tradePass)) {
        return false;
    }
    return true;
}

// 获取当前周开始日期和结束日期
function getWeekStartAndEnd()
{
    $today = date("Y-m-d");
    //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
    $first = 1;
    //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
    $w = date('w', strtotime($today));
    //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
    $start = date('Y-m-d', strtotime("$today -" . ($w ? $w - $first : 6) . ' days'));
    //本周结束日期
    $end = date('Y-m-d', strtotime("$start +6 days"));
    return [$start, $end];
}

// 获取最近活跃时间展示
function getShowDate($time)
{
    $todayStart = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
    if ($time > $todayStart) {
        return "今天";
    }
    $days = ceil(($todayStart - $time) / 3600 / 24);
    return $days . '天前';
}

// 验证日期格式
function checkDateFormat($date)
{
    if (date("Y-m-d", strtotime($date)) == $date) {
        return true;
    }
    return false;
}

// 获取上周-
function getLastWeekStartDate()
{
    return date("Y-m-d", time() - ((date("w")-1 + 7) * 86400));
}

// 获取上周日
function getLastWeekEndDate()
{
    return date("Y-m-d", time() - date("w") * 86400);
}

// 获取文件内容
function read_file($fname)
{
    $content = '';
    if (!file_exists($fname)) {
        return "";
    }
    $handle = fopen($fname, "rb");
    while (!feof($handle)) {
        $content .= fread($handle, 10000);
    }
    fclose($handle);
    return $content;
}

// 获取语言
function getLanguage()
{
    $lang = config("app.api_language");
    if (!$lang) {
        $lang = "zh-cn";
    }
    return $lang;
}