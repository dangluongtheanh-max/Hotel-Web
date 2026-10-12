<?php

declare(strict_types=1);

namespace Shared;

use Database\Database;
use PDO;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    /** @var array<class-string, true> */
    private array $resolving = [];

    public function get(string $class): object
    {
        if ($class === PDO::class) {
            return Database::getConnection();
        }

        if (isset($this->resolving[$class])) {
            throw new RuntimeException("Circular dependency detected while resolving {$class}");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$class} cannot be instantiated");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $this->resolving[$class] = true;
        try {
            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $arguments[] = $this->get($type->getName());
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(
                    "Cannot resolve parameter \${$parameter->getName()} for {$class}"
                );
            }

            return $reflection->newInstanceArgs($arguments);
        } finally {
            unset($this->resolving[$class]);
        }
    }
}
