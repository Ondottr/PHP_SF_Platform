<?php declare( strict_types=1 );
/*
 *  Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use App\Enums\AmqpQueueEnum;
use PHP_SF\System\Classes\Exception\InvalidRabbitMQConfigurationException;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class RabbitMQ
 *
 * @package PHP_SF\System\Database
 * @author  Dmytro Dyvulskyi <dmytro.dyvulskyi@nations-original.com>
 */
final class RabbitMQ
{

    /**
     * @var self[]
     */
    private static array $instances = [];


    private AMQPStreamConnection        $connection;
    private AMQPChannel&AbstractChannel $channel;


    private array $queues = [];

    private AmqpQueueEnum $queue;


    private function __construct( AmqpQueueEnum $queue )
    {
        $this->parseConfig();
        $this->setQueue( $queue );

        $this->connection = $this->createAmqpConnection();
        $this->channel    = $this->connection->channel();

        $this->initQueue();
    }


    public static function getInstance( AmqpQueueEnum $queue = AmqpQueueEnum::DEFAULT ): self
    {
        if ( array_key_exists( $queue->value, self::$instances ) === false )
            self::$instances[ $queue->value ] = ( new self( $queue ) );

        return self::$instances[ $queue->value ];
    }


    public function dispatch( string $message ): void
    {
        $this->channel->basic_publish(
            msg: new AMQPMessage( $message ),
            routing_key: $this->queue->value
        );
    }

    private function parseConfig(): void
    {
        // parse yaml config in config/packages/messenger.yaml to get list of queues(transports in symfony)
        $config = yaml_parse_file( project_dir() . '/config/packages/messenger.yaml' );

        $transports = $config['framework']['messenger']['transports'] ?? null;

        if ( empty( $transports ) )
            throw new InvalidRabbitMQConfigurationException( 'No transports found in config/packages/messenger.yaml' );


        foreach ( $transports as $transportName => $transport ) {
            $this->queues[ $transportName ] = null;
        }
    }

    private function setQueue( AmqpQueueEnum $queue ): self
    {
        if ( array_key_exists( $queue->value, $this->queues ) === false )
            throw new InvalidRabbitMQConfigurationException( "Queue $queue->value not found in config/packages/messenger.yaml" );

        $this->queue = $queue;

        return $this;
    }

    private function createAmqpConnection(): AMQPStreamConnection
    {
        /**
         * @var array{host: string, port: int, user: string, pass: string, path: string} $dsn
         */
        $dsn = parse_url( env( 'MESSENGER_TRANSPORT_DSN', 'amqp://guest:guest@localhost:7004' ) );

        return $this->connection = new AMQPStreamConnection(
            $dsn['host'],
            $dsn['port'],
            $dsn['user'],
            $dsn['pass'],
            '/'
        );
    }

    private function initQueue(): void
    {
        $this->channel->queue_declare( $this->queue->value, false, true, false, false );
    }


    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

}