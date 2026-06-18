<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;

$app = new Application(dirname(__DIR__));

$app->singleton(
    Kernel::class,
    Illuminate\Foundation\Http\Kernel::class,
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Illuminate\Foundation\Console\Kernel::class,
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class,
);

return $app;
