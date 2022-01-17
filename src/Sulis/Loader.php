<?php

declare(strict_types=1);

namespace Sulis;

use Exception;
use ReflectionClass;
use ReflectionException;

class Loader
{
    protected array $classes = [];
    protected array $instances = [];

    protected static array $directories = [];

    public function register(string $name, $class, array $params = [], ?callable $callback = null): void
    {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    public function unregister(string $name): void
    {
        unset($this->classes[$name]);
    }

    public function load(string $name, bool $shared = true): ?object
    {
        $object = null;

        if (isset($this->classes[$name])) {
            [$class, $params, $callback] = $this->classes[$name];
            $exists = isset($this->instances[$name]);

            if ($shared) {
                $object = $exists ? $this->getInstance($name) : $this->newInstance($class, $params);

                if (! $exists) {
                    $this->instances[$name] = $object;
                }
            } else {
                $object = $this->newInstance($class, $params);
            }

            if ($callback && (! $shared || ! $exists)) {
                call_user_func_array($callback, [&$object]);
            }
        }

        return $object;
    }

    public function getInstance(string $name): ?object
    {
        return $this->instances[$name] ?? null;
    }

    public function newInstance($class, array $params = []): object
    {
        if (is_callable($class)) {
            return call_user_func_array($class, $params);
        }

        $count = count($params);

        switch ($count) {
            case 0:  return new $class();
            case 1:  return new $class($params[0]);
            case 2:  return new $class($params[0], $params[1]);
            case 3:  return new $class($params[0], $params[1], $params[2]);
            case 4:  return new $class($params[0], $params[1], $params[2], $params[3]);
            case 5:  return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
            default: try {
                return (new ReflectionClass($class))->newInstanceArgs($params);
            } catch (ReflectionException $e) {
                throw new Exception("Cannot instantiate {$class}", 0, $e);
            }
        }
    }

    public function get(string $name)
    {
        return $this->classes[$name] ?? null;
    }

    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }

    public static function autoload(bool $enabled = true, array $directories = []): void
    {
        if ($enabled) {
            spl_autoload_register([__CLASS__, 'loadClass']);
        } else {
            spl_autoload_unregister([__CLASS__, 'loadClass']);
        }

        if (! empty($directories)) {
            self::addDirectory($directories);
        }
    }

    public static function loadClass(string $class): void
    {
        $file = str_replace(['\\', '_'], '/', $class) . '.php';

        foreach (self::$directories as $directory) {
            if (is_file($file = $directory . '/' . $file)) {
                require $file;
                return;
            }
        }
    }

    public static function addDirectory($directory): void
    {
        if (is_array($directory) || is_object($directory)) {
            foreach ($directory as $value) {
                self::addDirectory($value);
            }
        } elseif (is_string($directory)) {
            if (! in_array($directory, self::$directories, true)) {
                self::$directories[] = $directory;
            }
        }
    }
}
