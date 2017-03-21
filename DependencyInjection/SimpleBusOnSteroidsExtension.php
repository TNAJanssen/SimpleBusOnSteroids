<?php

namespace CleanCode\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class SimpleBusOnSteroidsExtension
 * @package AppBundle\Rabbitmq\Bundle
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class SimpleBusOnSteroidsExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $container->setParameter('empty_array', []);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $processor = new Processor();
        $configuration = new SimpleBusConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        if (!isset($config['simple_bus_on_steroids'])) {
            throw new \RuntimeException("You must provide config for 'simple_bus_on_steroids'. At least empty one.");
        }

        $container->setParameter('simple_bus.exception.requeue_max_times', $config['simple_bus_on_steroids']['exception']['requeue_max_times']);
        $container->setParameter('simple_bus.exception.requeue_time', $config['simple_bus_on_steroids']['exception']['requeue_time']);
        $container->setParameter('simple_bus.exception.requeue_multiply_by', $config['simple_bus_on_steroids']['exception']['requeue_multiply_by']);
        $container->setParameter('simple_bus.exception.dead_letter_exchange_name', $config['simple_bus_on_steroids']['exception']['dead_letter_exchange_name']);
        $container->setParameter('simple_bus.exception.dead_letter_queue_name', $config['simple_bus_on_steroids']['exception']['dead_letter_queue_name']);
        $container->setParameter('simple_bus_how_many_events_at_once', $config['simple_bus_on_steroids']['publisher']['how_many_to_retrieve_at_once']);
        $container->setParameter('simple_bus_send_messages_every_seconds', $config['simple_bus_on_steroids']['publisher']['send_messages_every_seconds']);
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new SimpleBusConfiguration();
    }
}