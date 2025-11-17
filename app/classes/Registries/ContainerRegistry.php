<?php

namespace App\Registries;

use App\Exceptions\SystemException;
use Psr\Container\ContainerInterface;

class ContainerRegistry
{
    /**
     * @var ContainerInterface|null
     */
    private static ?ContainerInterface $container = null;

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
        if (!self::$container instanceof ContainerInterface) {
            throw new SystemException('Container is not set.');
        }

        return self::$container->get($id);
    }
    public static function getContainer(): ContainerInterface
    {
        if (!self::$container instanceof ContainerInterface) {
            throw new SystemException('Container is not set.');
        }
        return self::$container;
    }
}
