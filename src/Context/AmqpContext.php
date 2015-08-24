<?php
namespace Boekkooi\Behat\AmqpExtension\Context;

use Behat\Behat\Context\Context;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Boekkooi\Bundle\AMQP\CommandConfiguration;
use Boekkooi\Bundle\AMQP\CommandMessage;
use Boekkooi\Bundle\AMQP\Command\QueueConsumeCommand;
use Boekkooi\Bundle\AMQP\Transformer\CommandTransformer;
use Boekkooi\Tactician\AMQP\Middleware\CommandTransformerMiddleware;
use Boekkooi\Tactician\AMQP\Middleware\PublishMiddleware;
use Boekkooi\Tactician\AMQP\Publisher\DirectPublisher;
use League\Tactician\CommandBus;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AmqpContext implements Context, KernelAwareContext
{
    use KernelDictionary;

    /**
     * Consume a queue for a specific amount of messages.
     *
     * @param string $vhost
     * @param string[] $queues
     * @param int $amount
     */
    public function consume($vhost, array $queues, $amount = 1)
    {
        $command = $this->getApplication()->find('amqp:consume');

        $tester = new CommandTester($command);
        $tester->execute([
            '--amount' => $amount,
            'vhost' => $vhost,
            'queues' => $queues,
        ]);

        if ($tester->getStatusCode() !== 0) {
            throw new \RuntimeException('amqp:consume failed to execute correctly');
        }
    }

    /**
     * Publish a command to a exchange.
     *
     * @param object $command
     * @param CommandConfiguration $commandConfiguration
     */
    public function publish($command, CommandConfiguration $commandConfiguration)
    {
        $exchange = $this->getExchangeByName($commandConfiguration->getVhost(), $commandConfiguration->getExchange());

        $commandTransformer = $this->getCommandTransformer();
        $commandTransformer->registerCommand($commandConfiguration);

        $commandTransformerMiddleware = new CommandTransformerMiddleware(
            $commandTransformer,
            [ get_class($command) ]
        );
        $publishMiddleware = new PublishMiddleware(new DirectPublisher($exchange));

        $bus = new CommandBus([$commandTransformerMiddleware, $publishMiddleware]);
        $bus->handle($command);
    }

    /**
     * @return Application
     */
    protected function getApplication()
    {
        $application = new Application($this->getKernel());
        $application->add(new QueueConsumeCommand());

        return $application;
    }

    /**
     * @return CommandTransformer
     */
    protected function getCommandTransformer()
    {
        $format = $this->getContainer()->getParameter('boekkooi.amqp.tactician.serializer.format');
        $serializer = $this->getCommandSerializer();

        return new CommandTransformer($serializer, $format);
    }

    /**
     * @return \Symfony\Component\Serializer\SerializerInterface
     */
    protected function getCommandSerializer()
    {
        $container = $this->getContainer();
        return $container->get(
            $container->getParameter('boekkooi.amqp.tactician.serializer.service')
        );
    }

    /**
     * @param string $vhost
     * @param string $exchangeName
     * @return \AMQPExchange
     */
    protected function getExchangeByName($vhost, $exchangeName)
    {
        $dummyMessage = new CommandMessage($vhost, $exchangeName, '');
        return $this->getContainer()
            ->get('boekkooi.amqp.tactician.exchange_locator')
            ->getExchangeForMessage($dummyMessage);
    }
}
