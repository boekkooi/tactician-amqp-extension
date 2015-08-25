<?php
namespace Boekkooi\Behat\AmqpExtension\ExchangeLocator;

use Boekkooi\Bundle\AMQP\ExchangeLocator\ContainerLocator;
use Boekkooi\Tactician\AMQP\AMQPAwareMessage;

class StaticContainerLocator extends ContainerLocator
{
    /**
     * @var \AMQPChannel[]
     */
    private static $channels;

    /**
     * @inheritdoc
     */
    protected function initializeChannel(AMQPAwareMessage $message)
    {
        $vhost = $message->getVHost();

        if (!isset(self::$channels[$vhost]) || !self::$channels[$vhost]->isConnected()) {
            self::$channels[$vhost] = parent::initializeChannel($message);
        }
        return self::$channels[$vhost];
    }
}
