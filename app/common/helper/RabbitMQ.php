<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-06-22
 * Time: 10:08
 */

namespace app\common\helper;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQ
{
    //直连交换机名前缀
    const DIRECT_EXCHANGE_PREFIX = "payment_service_direct_exchange:";

    //扇形交换机名前缀
    const FANOUT_EXCHANGE_PREFIX = "payment_service_fanout_exchange:";

    //队列名前缀
    const QUEUE_PREFIX = "payment_service_queue:";

    /**
     * 获取连接 rabbitMQ 的参数配置
     * @return mixed
     */
    private static function getRMQAccountConfig()
    {
        return config("account.rabbitMQ");
    }

    /**
     * @var AMQPStreamConnection
     */
    private static $connection = null;

    /**
     * @var AMQPChannel
     */
    private static $channel = null;

    /**
     * 连接rabbitMQ 并在该连接内创建一个渠道
     */
    private static function createConnectionAndChannel()
    {
        $config = self::getRMQAccountConfig();

        // 连接到rabbitMQ 的 broker
        $connection = new AMQPStreamConnection(
            $config["host"],
            $config["port"],
            $config["login"],
            $config["password"],
            $config["vhost"],
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            3.0,
            null,
            true);
        self::$connection = $connection;

        // 在连接内创建一个通道
        $channel = $connection->channel();

        self::$channel = $channel;
    }

    /**
     * 通过rabbitMQ 的直流交换机发送消息
     * @param $routingKey string  消息路由键 交换机和队列通过路由键绑定。消息路由键需要和队列路由键一致
     * @param $exchangeName string 交换机名称  务必需要和消费者队列使用同一交换机
     * @param $msg string 需要发送的数据
     */
    public static function directProduce($routingKey, $exchangeName, $msg)
    {
        if (self::$channel == null || !self::$connection->isConnected() || !self::$channel->is_open()) {
            self::createConnectionAndChannel();
        }

        /**
         * 创建交换机$channel->exchange_declare($exhcange_name,$type,$passive,$durable,$auto_delete);
         * type: 交换机类型， direct代表直流交换机
         * passive: 消极处理， false:判断是否存在，不存在创建，存在判断配置是否相同，配置不相同会报错。
         * durable：true、false true：服务器重启会保留下来Exchange。警告：仅设置此选项，不代表消息持久化。即不保证重启后消息还在
         * autoDelete：true、false.true:当已经没有消费者时，服务器是否可以删除该Exchange
         */
        self::$channel->exchange_declare($exchangeName, "direct", false, true, false);

        // 为消息配置属性
        // 声明消息持久，持久的队列 + 持久的消息在RabbitMQ重启后才不会丢失
        $messageAttributes = [
            "delivery_mode" => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ];
        $AMQPMessage = new AMQPMessage($msg, $messageAttributes);

        self::$channel->basic_publish($AMQPMessage, $exchangeName, $routingKey);
    }

    /**
     * 通过rabbitMQ 的直流交换机接收消息
     * @param $routingKey string  消息路由键 交换机和队列通过路由键绑定。路由键需要和生产者消息路由键一致
     * @param $exchangeName string 队列需要绑定到那个交换机
     * @param $queueName string 队列名称，唯一即可
     * @param $callback callable 收到消息后的回调方法
     * @throws \ErrorException
     */
    public static function directConsumer($routingKey, $exchangeName, $queueName, $callback)
    {
        if (self::$channel == null || !self::$connection->isConnected() || !self::$channel->is_open()) {
            self::createConnectionAndChannel();
        }

        /**
         * 创建交换机，务必需要和生产者使用同一交换机
         */
        self::$channel->exchange_declare($exchangeName, "direct", false, true, false);

        /**
         * 创建队列
         * @param string $queue     队列名称
         * @param bool $passive     false:存在判断配置是否相同，不存在创建
         * @param bool $durable     是否是持久化队列
         * @param bool $exclusive   是否为当前连接的专用队列，在连接断开后，会自动删除该队列
         * @param bool $auto_delete 当没有任何消费者使用时，自动删除该队列
         */
        self::$channel->queue_declare($queueName, false, true, false, false);

        /**
         * 把队列绑定到指定的交换机
         */
        self::$channel->queue_bind($queueName, $exchangeName, $routingKey);

        /**
         * 指定 QoS  一旦启用，如果有消息未被显示确认，则队列进程会"卡死"
         * @param int $prefetch_size
         * @param int $prefetch_count 告诉RabbitMQ不要同时给一个消费者推送多于N个消息，
         *      即一旦有N个消息还没有ack，则该consumer将block掉，直到有消息ack。
         * @param bool $a_global 是否将上面设置应用于channel，简单点说，就是上面限制是channel级别的还是consumer级别
         */
//        self::$channel->basic_qos(0, 1, false);
        /**
         *
         * @param string $queue             队列名称，唯一即可
         * @param string $consumer_tag      消费者标签
         * @param bool $no_local
         * @param bool $no_ack              false 收到消息后，必须需要回复确认被消费，以便服务器把消息真正删除掉
         * @param bool $exclusive           排他消费者,即这个队列只能由一个消费者消费.适用于任务不允许进行并发处理的情况下
         * @param bool $nowait              不返回执行结果,但是如果排他开启的话,则必须需要等待结果的,如果两个一起开就会报错
         * @param callable|null $callback   回调函数
         * @param int|null $ticket
         * @param array $arguments
         */
        self::$channel->basic_consume($queueName,
            "",
            false,
            false,
            false,
            false,
            $callback);

        while (count(self::$channel->callbacks)) {
            self::$channel->wait();
        }
    }

    /**
     * 显示确认，队列接收到显示确认后会删除该消息
     * @param AMQPMessage $message
     */
    public static function ackMessage(AMQPMessage $message)
    {
        $message->delivery_info["channel"]->basic_ack($message->delivery_info["delivery_tag"]);
    }

    /**
     * 拒绝消息
     * @param AMQPMessage $message
     * @param bool $requeue 是否把消息重新放回队列
     */
    public static function rejectMessage(AMQPMessage $message, $requeue = true)
    {
        $message->delivery_info["channel"]->basic_reject($message->delivery_info["delivery_tag"], $requeue);
    }
}