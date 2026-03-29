<?php declare(strict_types=1);

namespace PHP_SF\System\Database;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Enums\Amqp\QueueEnum;

/**
 * Class RabbitMQConsumer
 *
 * @package PHP_SF\System\Database
 */
final class RabbitMQConsumer
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    public function __construct()
    {
        $dsn = parse_url(env('MESSENGER_TRANSPORT_DSN', 'amqp://guest:guest@localhost:7004'));

        $this->connection = new AMQPStreamConnection(
            $dsn['host'],
            $dsn['port'],
            $dsn['user'],
            $dsn['pass'],
            '/'
        );
        $this->channel = $this->connection->channel();
    }

    public function consume(QueueEnum $queue, callable $callback): void
    {
        $this->channel->queue_declare($queue->value, false, true, false, false);

        $internalCallback = function (AMQPMessage $msg) use ($callback) {
            $data = json_decode($msg->getBody(), true);
            $callback($data);
        };

        $this->channel->basic_consume($queue->value, '', false, true, false, false, $internalCallback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
