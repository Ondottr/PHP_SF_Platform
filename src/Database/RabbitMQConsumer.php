<?php declare(strict_types=1);
/*
 *  Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 *  Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 *  granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 *  THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 *  INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 *  LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 *  RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 *  TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Database;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Enums\Amqp\QueueEnum;

/**
 * Class RabbitMQConsumer
 *
 * @package PHP_SF\System\Database
 * @author  Dmytro Dyvulskyi <dmytro.dyvulskyi@nations-original.com>
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
