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


/**
 * 用户填写邀请人后续处理
 * @param $userId
 */
function userAddParentCallbackProduce($userId)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_add_parent";
    $routingKey = "user_add_parent";
    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
        "u_id" => $userId,
    ]));
}

function userAddParentCallbackConsumer($callback)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_add_parent";
    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_add_parent";
    $routingKey = "user_add_parent";

    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);
}

/**
 * 通话结束后续处理
 * @param $chatId
 */
function chatEndCallbackProduce($chatId)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "chat_end";
    $routingKey = "chat_end";
    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
        "chat_id" => $chatId,
    ]));
}

function chatEndCallbackConsumer($callback)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "chat_end";
    $queueName = RABBIT_MQ_QUEUE_PREFIX . "chat_end";
    $routingKey = "chat_end";

    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);
}

/**
 * 用户访问后续处理
 *
 * @param $userId
 * @param $visitorId
 */
function userVisitorCallbackProduce($userId, $visitorId)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_visitor_callback";
    $routingKey = "user_visitor_callback";
    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
        'uid' => $userId, 'vid' => $visitorId
    ]));
}

function userVisitorCallBackConsumer($callback)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_visitor_callback";
    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_visitor_callback";
    $routingKey = "user_visitor_callback";

    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);
}

/**
 * 聊天送礼物计算魅力值回调
 *
 * @param $incomeUserId
 * @param $spendUserId
 * @param $coin
 */
function userGuardCallbackProduce($incomeUserId, $spendUserId, $coin)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_guard_callback";
    $routingKey = "user_guard_callback";
    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
        'incomeUserId' => $incomeUserId, 'spendUserId' => $spendUserId, 'coin' => $coin
    ]));
}

function userGuardCallBackConsumer($callback)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_guard_callback";
    $queueName = RABBIT_MQ_QUEUE_PREFIX . "user_guard_callback";
    $routingKey = "user_guard_callback";

    RabbitMQ::directConsumer($routingKey, $exchangeName, $queueName, $callback);
}