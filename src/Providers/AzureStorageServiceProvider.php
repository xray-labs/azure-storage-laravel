<?php

declare(strict_types=1);

namespace Xray\AzureStorageLaravel\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Xray\AzureStorageLaravel\Adapters\BlobStorageAdapter;
use Xray\AzureStorageLaravel\Factories\BlobStorageFactory;

class AzureStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Storage::extend('azure', function (Application $app, array $config) {
            /** @var BlobStorageFactory $factory */
            $factory = $app->make(BlobStorageFactory::class, ['config' => $config]);
            $adapter = $app->make(BlobStorageAdapter::class, [
                'client'    => $factory->createClient(),
                'container' => $factory->getContainer(),
            ]);

            /** @var FilesystemAdapter */
            return $app->make(FilesystemAdapter::class, [
                'driver'  => $app->make(Filesystem::class, ['adapter' => $adapter, 'config' => $config]),
                'adapter' => $adapter,
                'config'  => $config,
            ]);
        });
    }
}
