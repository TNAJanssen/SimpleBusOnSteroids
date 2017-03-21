<?php

namespace CleanCode\SimpleBusOnSteroids\Middleware\MessageSubscriberDispatcher;

use CleanCode\SimpleBusOnSteroids\ContextHolder;
use CleanCode\SimpleBusOnSteroids\Middleware\EventStore\EventStoreMiddleware;
use CleanCode\SimpleBusOnSteroids\Subscriber\SubscriberInformationHolder;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use SimpleBus\Message\Bus\Middleware\MessageBusMiddleware;
use SimpleBus\Message\Subscriber\Resolver\MessageSubscribersResolver;

/**
 * Class MessageHandler
 * @package CleanCode\SimpleBusOnSteroids\Middleware\MessageHandler
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class MessageSubscriberDispatcher implements MessageBusMiddleware
{
    const SUBSCRIBER_HANDLE_METHOD = 'handle';

    /**
     * @var MessageSubscribersResolver
     */
    private $messageSubscribersResolver;
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;
    /**
     * @var SubscriberInformationHolder
     */
    private $subscriberInformationHolder;
    /**
     * @var ContextHolder
     */
    private $contextHolder;
    /**
     * @var SubscriberHandledEventRepository
     */
    private $subscriberHandledEventRepository;

    /**
     * MessageSubscriberDispatcher constructor.
     * @param ManagerRegistry $managerRegistry
     * @param MessageSubscribersResolver $messageSubscribersResolver
     * @param SubscriberInformationHolder $subscriberInformationHolder
     * @param ContextHolder $contextHolder
     * @param SubscriberHandledEventRepository $subscriberHandledEventRepository
     */
    public function __construct(
        ManagerRegistry $managerRegistry, MessageSubscribersResolver $messageSubscribersResolver,
        SubscriberInformationHolder $subscriberInformationHolder, ContextHolder $contextHolder,
        SubscriberHandledEventRepository $subscriberHandledEventRepository
    )
    {
        $this->managerRegistry = $managerRegistry;
        $this->messageSubscribersResolver = $messageSubscribersResolver;
        $this->subscriberInformationHolder = $subscriberInformationHolder;
        $this->contextHolder = $contextHolder;
        $this->subscriberHandledEventRepository = $subscriberHandledEventRepository;
    }

    /**
     * @inheritDoc
     */
    public function handle($message, callable $next)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->managerRegistry->getManager();
        $messageSubscribers = $this->messageSubscribersResolver->resolve($message);
        $exception = null;

        foreach ($messageSubscribers as $messageSubscriber) {
            if (!array_key_exists(0, $messageSubscriber) || !$this->isMessageSubscriber($messageSubscriber[0])) {
                throw new \RuntimeException("Passed message subscriber doesn't have handle method");
            }

            $messageSubscriber = $messageSubscriber[0];
            $entityManager->beginTransaction();
            try {
                $currentEventId = $this->contextHolder->currentContext()->currentlyHandledEventId();
                $subscriberInformation = $this->subscriberInformationHolder->findFor($messageSubscriber);

                if (!$this->isAlreadyHandled($subscriberInformation, $currentEventId)) {
                    $messageSubscriber->{self::SUBSCRIBER_HANDLE_METHOD}($message);

                    $this->subscriberHandledEventRepository->save(SubscriberHandledEvent::createWithSubscriberInformation(
                        $subscriberInformation, $currentEventId
                    ));
                }

                $entityManager->flush();
                $entityManager->commit();
            }catch (\Exception $e) {
                $entityManager->rollback();
                $this->managerRegistry->resetManager();

                $exception =  $e;
            }
        }

        if ($exception) {
            throw $exception;
        }

        $next($message);
    }

    /**
     * @param $messageSubscriber
     * @return bool
     */
    private function isMessageSubscriber($messageSubscriber) : bool
    {
        return method_exists($messageSubscriber, self::SUBSCRIBER_HANDLE_METHOD);
    }

    /**
     * @param $subscriberInformation
     * @param $currentEventId
     * @return bool
     */
    private function isAlreadyHandled($subscriberInformation, $currentEventId) : bool
    {
        return (bool)$this->subscriberHandledEventRepository->findFor($subscriberInformation, $currentEventId);
    }
}