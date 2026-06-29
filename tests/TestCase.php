<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Bazowy TestCase — integracja HTTP + PostgreSQL (read model).
 *
 * ZAKAZ: RefreshDatabase, migracje, seedery, INSERT/UPDATE/DELETE/TRUNCATE, Schema::*.
 * Zob. tests/README.md i .cursor/rules/api-tests.mdc
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
