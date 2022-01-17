<?php

declare(strict_types=1);

namespace Sulis;

use Exception;
use InvalidArgumentException;

class Dispatcher
{
    protected array $events = [];
    protected array $filters = [];

    final public function run(string $name, array $params = [])
    {
        $output = '';

        if (! empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        $output = self::execute($this->get($name), $params);

        if (! empty($this->filters[$name]['after'])) {
            $this->filter($this->filters[$name]['after'], $params, $output);
        }

        return $output;
    }

    final public function set(string $name, callable $callback): void
    {
        $this->events[$name] = $callback;
    }

    final public function get(string $name): ?callable
    {
        return $this->events[$name] ?? null;
    }

    final public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    final public function clear(?string $name = null): void
    {
        if (null !== $name) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
        } else {
            $this->events = [];
            $this->filters = [];
        }
    }

    final public function hook(string $name, string $type, callable $callback): void
    {
        $this->filters[$name][$type][] = $callback;
    }

    final public function filter(array $filters, array &$params, &$output): void
    {
        $args = [&$params, &$output];

        foreach ($filters as $callback) {
            $continue = self::execute($callback, $args);

            if (false === $continue) {
                break;
            }
        }
    }

    public static function execute($callback, array &$params = [])
    {
        if (is_callable($callback)) {
            return is_array($callback)
                ? self::invokeMethod($callback, $params)
                : self::callFunction($callback, $params);
        }

        throw new InvalidArgumentException('Invalid callback specified.');
    }

    public static function callFunction($func, array &$params = [])
    {
        if (is_string($func) && false !== strpos($func, '::')) {
            return call_user_func_array($func, $params);
        }

        $count = count($params);

        switch ($count) {
            case 0:  return $func();
            case 1:  return $func($params[0]);
            case 2:  return $func($params[0], $params[1]);
            case 3:  return $func($params[0], $params[1], $params[2]);
            case 4:  return $func($params[0], $params[1], $params[2], $params[3]);
            case 5:  return $func($params[0], $params[1], $params[2], $params[3], $params[4]);
            default: return call_user_func_array($func, $params);
        }
    }

    public static function invokeMethod($func, array &$params = [])
    {
        [$class, $method] = $func;
        $instance = is_object($class);
        $count = count($params);

        switch ($count) {
            case 0:
                return $instance
                    ? $class->$method()
                    : $class::$method();

            case 1:
                return $instance
                    ? $class->$method($params[0])
                    : $class::$method($params[0]);

            case 2:
                return $instance
                    ? $class->$method($params[0], $params[1])
                    : $class::$method($params[0], $params[1]);

            case 3:
                return $instance
                    ? $class->$method($params[0], $params[1], $params[2])
                    : $class::$method($params[0], $params[1], $params[2]);

            case 4:
                return $instance
                    ? $class->$method($params[0], $params[1], $params[2], $params[3])
                    : $class::$method($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $instance
                    ? $class->$method($params[0], $params[1], $params[2], $params[3], $params[4])
                    : $class::$method($params[0], $params[1], $params[2], $params[3], $params[4]);

            default:
                return call_user_func_array($func, $params);
        }
    }

    final public function reset(): void
    {
        $this->events = [];
        $this->filters = [];
    }
}
