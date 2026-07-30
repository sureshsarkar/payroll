<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Hard guard against running tests on the production database.
     *
     * Why this exists: Laravel's RefreshDatabase trait runs migrate:fresh,
     * which drops every table. If phpunit.xml doesn't override DB_DATABASE,
     * tests inherit the prod connection from .env and silently destroy
     * live data. We hit this on 2026-05-07 — a leftover Breeze ProfileTest
     * with `use RefreshDatabase` wiped the operator's mbs.sql restore.
     *
     * The check reads DB_DATABASE from the environment directly (NOT from
     * the Laravel config helper) because this runs BEFORE parent::setUp()
     * boots the framework. If the resolved DB name matches the production
     * names we know about, refuse to start.
     */
    protected function setUp(): void
    {
        $db = (string) ($_ENV['DB_DATABASE']
            ?? $_SERVER['DB_DATABASE']
            ?? getenv('DB_DATABASE')
            ?: '');

        $looksProd = preg_match('/^(mbs|mbsguru|prod|production)$/i', $db) === 1;

        if ($looksProd) {
            throw new \RuntimeException(
                "REFUSING to run tests against the production database '{$db}'. " .
                "Create a separate test DB and override DB_DATABASE in phpunit.xml " .
                "(e.g. <env name=\"DB_DATABASE\" value=\"mbs_test\"/>) or use " .
                ".env.testing. Tests using RefreshDatabase / DatabaseMigrations " .
                "will run migrate:fresh which drops every table."
            );
        }

        parent::setUp();
    }
}
