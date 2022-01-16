<?php

declare(strict_types=1);

use Sulis\Loader;

require_once __DIR__ . '/Loader.php';

Loader::autoload(true, [dirname(__DIR__)]);
