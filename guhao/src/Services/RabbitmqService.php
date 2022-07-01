<?php


namespace guhao\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitmqService
{

    /**
     * 入消息队列
     * @param $queue 队列名
     * @param null $data 数据
     * @param string $exchange 交换机名称
     * @param string $routingKey 路由键
     */
    public static function pushMessageQueue($queue, $data = null, $exchange = '', $routingKey = '')
    {
        $config = config('queue.connections.rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['login'],
            $config['password'],
            $config['vhost']
        );
        $channel = $connection->channel();

        $table = new AMQPTable([
            'x-queue-type' => 'classic'
        ]);

        //设为confirm模式
        $channel->confirm_select();

        //声明队列
        $channel->queue_declare($queue, false, true, false, false, false, $table);

        if ($exchange && $routingKey) {
            //声明交换机
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
            //路由键绑定
            $channel->queue_bind($queue, $exchange, $routingKey);
        }

        $message = new AMQPMessage(json_encode($data, JSON_UNESCAPED_UNICODE), array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange, $routingKey);

        //阻塞等待消息确认
        $channel->wait_for_pending_acks();

        $channel->close();
        try {
            $connection->close();
        } catch (\Exception $e) {
            var_dump($e);
        }
    }
}
