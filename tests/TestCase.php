<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application for the test suite.
     */
    public function createApplication(): Application
    {
        $testingEnvironmentFile = __DIR__.'/../.env.testing';

        throw_unless(is_file($testingEnvironmentFile), RuntimeException::class, 'The .env.testing file is required to run the Laravel test suite. Run composer setup first.');

        return parent::createApplication();
    }
}
