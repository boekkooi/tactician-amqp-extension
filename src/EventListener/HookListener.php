<?php
namespace Boekkooi\Behat\AmqpExtension\EventListener;

use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\FeatureTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Testwork\EventDispatcher\Event\AfterExerciseAborted;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Behat\Testwork\EventDispatcher\Event\LifecycleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Boekkooi\Behat\AmqpExtension\Service\AmqpService;

class HookListener implements EventSubscriberInterface
{
    /**
     * @var AmqpService
     */
    private $amqpService;

    /**
     * Constructor
     *
     * @param string $lifetime
     * @param AmqpService $amqpService
     */
    public function __construct($lifetime, AmqpService $amqpService)
    {
        $this->lifetime = $lifetime;
        $this->amqpService = $amqpService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ExerciseCompleted::BEFORE => 'beforeExercise',

            FeatureTested::BEFORE     => 'before',
            ExampleTested::BEFORE     => 'before',
            ScenarioTested::BEFORE    => 'before',

            ExerciseCompleted::AFTER  => 'afterExercise',
        ];
    }

    /**
     * Listens to "exercise.before" event.
     */
    public function beforeExercise()
    {
        $this->amqpService->declareVhosts();
    }

    /**
     * Listens to "(feature|scenario|example).before" event.
     *
     * @param LifecycleEvent $event
     */
    public function before(LifecycleEvent $event)
    {
        if (
            'feature' === $this->lifetime && $event instanceof FeatureTested ||
            'scenario' === $this->lifetime && $event instanceof ScenarioTested
        ) {
            $this->amqpService->purgeQueues();
        }
    }

    public function afterExercise(ExerciseCompleted $event)
    {
        if ($event instanceof AfterExerciseAborted) {
            // Do not purge when the test was aborted
            // (someone may want to debug the .... out of it)
            return;
        }

        $this->amqpService->purgeQueues();
    }
}
