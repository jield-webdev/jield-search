<?php

declare(strict_types=1);

namespace Jield\Search\Factory;

use Jield\Search\Service\SearchUpdateService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class SearchQueueServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SearchUpdateService
    {
        return new SearchUpdateService(
            container: $container,
            bus: $container->get('Jield\Search\Bus'),
            cores: $container->get('Config'),
        );
    }
}
