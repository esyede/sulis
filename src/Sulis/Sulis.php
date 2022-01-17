<?php

declare(strict_types=1);

use Sulis\Dispatcher;
use Sulis\Engine;
use Sulis\Request;
use Sulis\Response;
use Sulis\Router;
use Sulis\View;

class Sulis
{
    private static Engine $engine;
    private static bool $initialized = false;

    private function __construct()
    {
        //
    }

    private function __destruct()
    {
        //
    }

    private function __clone()
    {
        //
    }

    public static function __callStatic(string $name, array $params)
    {
        $app = self::app();
        return Dispatcher::invokeMethod([$app, $name], $params);
    }

    public static function app(): Engine
    {
        if (! static::$initialized) {
            require_once __DIR__ . '/autoload.php';
            self::$engine = new Engine();
            static::$initialized = true;
        }

        return self::$engine;
    }
}
