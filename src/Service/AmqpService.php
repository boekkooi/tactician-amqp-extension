<?php
namespace Boekkooi\Behat\AmqpExtension\Service;

use Boekkooi\Bundle\AMQP\DependencyInjection\BoekkooiAMQPExtension;
use Boekkooi\Bundle\AMQP\Tools\SchemaTool;
use Symfony\Component\HttpKernel\KernelInterface;

class AmqpService
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function declareVhosts()
    {
        $vhosts = $this->getVhostNames();
        foreach ($vhosts as $vhost) {
            $schemaTool = new SchemaTool($this->getVhostConnection($vhost));
            $schemaTool->declareDefinitions(
                $this->getVHostExchanges($vhost),
                $this->getVHostQueues($vhost)
            );
        }
    }

    /**
     * purge all queues know to within the container.
     */
    public function purgeQueues()
    {
        $vhosts = $this->getVhostNames();
        foreach ($vhosts as $vhost) {
            $schemaTool = new SchemaTool($this->getVhostConnection($vhost));
            $schemaTool->purgeQueues(
                $this->getVHostQueues($vhost)
            );
        }
    }

    /**
     * @return string[]
     */
    private function getVHostNames()
    {
        return $this->getContainer()
            ->getParameter(BoekkooiAMQPExtension::PARAMETER_VHOST_LIST);
    }

    /**
     * @param string $vhost
     * @return \Boekkooi\Bundle\AMQP\LazyConnection
     */
    private function getVhostConnection($vhost)
    {
        return $this->getContainer()->get(sprintf(
            BoekkooiAMQPExtension::SERVICE_VHOST_CONNECTION_ID,
            $vhost
        ));
    }

    /**
     * @param string $vhost
     * @return \Boekkooi\Bundle\AMQP\LazyExchange[]
     */
    private function getVHostExchanges($vhost)
    {
        return $this->getServicesByParameterWithNames(
            sprintf(BoekkooiAMQPExtension::PARAMETER_VHOST_EXCHANGE_LIST, $vhost),
            BoekkooiAMQPExtension::SERVICE_VHOST_EXCHANGE_ID,
            [ $vhost ]
        );
    }

    /**
     * @param string $vhost
     * @return \Boekkooi\Bundle\AMQP\LazyQueue[]
     */
    private function getVHostQueues($vhost)
    {
        return $this->getServicesByParameterWithNames(
            sprintf(BoekkooiAMQPExtension::PARAMETER_VHOST_QUEUE_LIST, $vhost),
            BoekkooiAMQPExtension::SERVICE_VHOST_QUEUE_ID,
            [ $vhost ]
        );
    }

    private function getServicesByParameterWithNames($parameter, $serviceNameFormat, array $serviceNameArgs = [])
    {
        $container = $this->getContainer();
        $names = $container->getParameter($parameter);

        $services = [];
        foreach ($names as $name) {
            $services[] = $container->get(vsprintf(
                $serviceNameFormat,
                array_merge($serviceNameArgs, [ $name ])
            ));
        }

        return $services;
    }

    private function getContainer()
    {
        return $this->kernel->getContainer();
    }
}
