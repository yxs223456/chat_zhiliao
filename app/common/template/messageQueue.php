<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-07-20
 * Time: 19:40
 */

use app\common\helper\RabbitMQ;

// rabbitmq 直连交换机名前缀
define("RABBIT_MQ_DIRECT_EXCHANGE_PREFIX", "chat_direct_exchange:");

// rabbitmq 扇形交换机名前缀
define("RABBIT_MQ_FANOUT_EXCHANGE_PREFIX", "chat_fanout_exchange:");

// rabbitmq 队列名前缀
define("RABBIT_MQ_QUEUE_PREFIX", "chat_queue:");

// redis message queue 前缀
define("REDIS_KEY_MQ_KEY_PREFIX", REDIS_KEY_PREFIX . "message_queue:");

// 阻塞等待时长 30 秒
define("REDIS_KEY_MQ_WAIT_TIME", 5);

/**
 * 用户充值后回调
 */
define("REDIS_MQ_KEY_RECHARGE", REDIS_KEY_MQ_KEY_PREFIX . "recharge");

function rechargeCallbackProduce($userId, \Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "recharge";
//    $routingKey = "recharge";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        "u_id" => $userId,
//    ]));
    $key = REDIS_MQ_KEY_RECHARGE;
    $data = json_encode([
        "u_id" => $userId,
    ]);
    $redis->rPush($key, $data);
}

function rechargeCallbackConsumer(\Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "recharge";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "recharge";
//    $routingKey = "recharge";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_RECHARGE;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}

/**
 * 用户登录、退出后回调
 */
define("REDIS_MQ_KEY_LOGIN_AND_LOGOUT", REDIS_KEY_MQ_KEY_PREFIX . "loginAndLogout");

function loginAndLogoutCallbackProduce($userId, $do, \Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "loginAndLogout";
//    $routingKey = "loginAndLogout";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        "u_id" => $userId,
//        "do" => $do
//    ]));

    $key = REDIS_MQ_KEY_LOGIN_AND_LOGOUT;
    $data = json_encode([
        "u_id" => $userId,
        "do" => $do
    ]);
    $redis->rPush($key, $data);
}

function loginAndLogoutCallbackConsumer(\Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "loginAndLogout";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "loginAndLogout";
//    $routingKey = "loginAndLogout";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_LOGIN_AND_LOGOUT;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}

/**
 * 用户填写邀请人后续处理
 */
define("REDIS_MQ_KEY_USER_ADD_PARENT", REDIS_KEY_MQ_KEY_PREFIX . "userAddParent");

function userAddParentCallbackProduce($userId, \Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_add_parent";
//    $routingKey = "user_add_parent";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        "u_id" => $userId,
//    ]));

    $key = REDIS_MQ_KEY_USER_ADD_PARENT;
    $data = json_encode([
        "u_id" => $userId,
    ]);
    $redis->rPush($key, $data);
}

function userAddParentCallbackConsumer(\Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_add_parent";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_add_parent";
//    $routingKey = "user_add_parent";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_USER_ADD_PARENT;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}

/**
 * 通话结束后续处理
 */
define("REDIS_MQ_KEY_CHAT_END", REDIS_KEY_MQ_KEY_PREFIX . "chatEnd");

function chatEndCallbackProduce($chatId, \Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "chat_end";
//    $routingKey = "chat_end";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        "chat_id" => $chatId,
//    ]));

    $key = REDIS_MQ_KEY_CHAT_END;
    $data = json_encode([
        "chat_id" => $chatId,
    ]);
    $redis->rPush($key, $data);
}

function chatEndCallbackConsumer(\Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "chat_end";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "chat_end";
//    $routingKey = "chat_end";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_CHAT_END;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}

/**
 * 用户访问后续处理
 *
 * @param $userId
 * @param $visitorId
 */
define("REDIS_MQ_KEY_VISITOR_CALLBACK", REDIS_KEY_MQ_KEY_PREFIX . "visitorCallBack");
function userVisitorCallbackProduce($userId, $visitorId, Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_visitor_callback";
//    $routingKey = "user_visitor_callback";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        'uid' => $userId, 'vid' => $visitorId
//    ]));

    $key = REDIS_MQ_KEY_VISITOR_CALLBACK;
    $data = json_encode([
        "uid" => $userId,
        'vid' => $visitorId
    ]);
    $redis->rPush($key, $data);
}

function userVisitorCallBackConsumer(Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_visitor_callback";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_visitor_callback";
//    $routingKey = "user_visitor_callback";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_VISITOR_CALLBACK;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}

/**
 * 聊天送礼物计算魅力值回调
 *
 * @param $incomeUserId
 * @param $spendUserId
 * @param $coin
 */
define("REDIS_MQ_KEY_GUARD_CALLBACK", REDIS_KEY_MQ_KEY_PREFIX . "guardCallBack");
function userGuardCallbackProduce($incomeUserId, $spendUserId, $coin, Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_guard_callback";
//    $routingKey = "user_guard_callback";
//    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
//        'incomeUserId' => $incomeUserId, 'spendUserId' => $spendUserId, 'coin' => $coin
//    ]));

    $key = REDIS_MQ_KEY_GUARD_CALLBACK;
    $data = json_encode([
        "incomeUserId" => $incomeUserId,
        'spendUserId' => $spendUserId,
        'coin' => $coin
    ]);
    $redis->rPush($key, $data);
}

function userGuardCallBackConsumer(Redis $redis)
{
//    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_guard_callback";
//    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_guard_callback";
//    $routingKey = "user_guard_callback";
//
//    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);

    $key = REDIS_MQ_KEY_GUARD_CALLBACK;
    $data = $redis->blPop([$key], REDIS_KEY_MQ_WAIT_TIME);

    if ($data == null || empty($data[1])) {
        return null;
    }
    $arr = json_decode($data[1], true);
    return $arr;
}