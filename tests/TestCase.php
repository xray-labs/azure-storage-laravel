<?php

namespace Xray\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Xray\AzureStorageLaravel\Providers\AzureStorageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders(mixed $app): array
    {
        return [
            AzureStorageServiceProvider::class,
        ];
    }
}
