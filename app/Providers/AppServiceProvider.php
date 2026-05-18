<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register as singletons so the formatter registry is shared across
        // the entire request lifecycle and custom formatters registered in one
        // place (e.g. another ServiceProvider) are visible everywhere.
        $this->app->singleton(\App\Http\Response\FormatterFactory::class);
        $this->app->singleton(\App\Http\Response\ApiResponseRenderer::class);
    }

    public function boot(): void
    {
        // Example: register additional formatters after the factory is built.
        // $factory = $this->app->make(\App\Http\Response\FormatterFactory::class);
        // $factory->register('csv',  \App\Http\Response\Formatters\CsvFormatter::class);
        // $factory->register('yaml', \App\Http\Response\Formatters\YamlFormatter::class);
    }
}
