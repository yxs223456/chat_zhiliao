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


// 用户注册后续处理
function userRegisterCallback($userId)
{
    $exchangeName = RABBIT_MQ_DIRECT_EXCHANGE_PREFIX . "user_register_callback";
    $routingKey = "user_register_callback";
    RabbitMQ::directProduce($routingKey, $exchangeName, json_encode([
        "u_id" => $userId,
    ]));
}