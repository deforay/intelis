<?php

namespace App\Registries;

use App\Exceptions\SystemException;
use Psr\Container\ContainerInterface;

class ContainerRegistry
{
    /**
     * @var ContainerInterface|null
     */
    private static ?ContainerInterface $container;

    /**
     * Set the container instance.
     *
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Get a service from the container.
     *
     * @param string $id
     * @return mixed
     */
    public static function get(string $id)
    {
        if (self::$container === null) {
            throw new SystemException('Container is not set.');
        }

        return self::$container->get($id);
    }
}
