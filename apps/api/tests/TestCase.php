<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // URLs herdadas não podem substituir o banco isolado por outro banco.
        $app['config']->set(['database.connections.sqlite.url' => null, 'database.connections.mysql.url' => null]);
        if (getenv('TEST_DB_CONNECTION') !== 'mysql') {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        } else {
            if (! str_ends_with(getenv('TEST_DB_DATABASE') ?: 'factorycontrol_pro_test', '_test')) {
                throw new \RuntimeException('O banco de testes MySQL deve terminar em _test.');
            }
            $app['config']->set(['database.default' => 'mysql', 'database.connections.mysql.database' => getenv('TEST_DB_DATABASE') ?: 'factorycontrol_pro_test']);
        }

        return $app;
    }
}
