<?php
namespace Boekkooi\Behat\AmqpExtension\ServiceContainer;

use Behat\Symfony2Extension\ServiceContainer\Symfony2Extension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Boekkooi\Behat\AmqpExtension\EventListener\HookListener;
use Boekkooi\Behat\AmqpExtension\Service\AmqpService;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtension implements ExtensionInterface
{
    const KERNEL_ID = 'symfony2_extension.kernel';

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return 'amqp';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('lifetime')
                    ->defaultValue('feature')
                    ->validate()
                        ->ifNotInArray(['feature', 'scenario'])
                        ->thenInvalid('Invalid fixtures lifetime "%s"')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $container->setParameter('amqp_extension.lifetime', $config['lifetime']);

        $this->loadAmqpService($container);
        $this->loadHookListener($container);
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    private function loadAmqpService(ContainerBuilder $container)
    {
        $definition = new Definition(AmqpService::class, [
            new Reference(Symfony2Extension::KERNEL_ID),
        ]);
        $container->setDefinition('amqp_extension.amqp_service', $definition);
    }

    private function loadHookListener(ContainerBuilder $container)
    {
        $definition = new Definition(HookListener::class, [
            '%amqp_extension.lifetime%',
            new Reference('amqp_extension.amqp_service'),
        ]);
        $definition->addTag('event_dispatcher.subscriber');
        $container->setDefinition('amqp_extension.hook_event_listener', $definition);
    }
}
