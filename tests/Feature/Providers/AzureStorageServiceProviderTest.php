<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Xray\AzureStorageLaravel\Adapters\BlobStorageAdapter;
use Xray\AzureStorageLaravel\Providers\AzureStorageServiceProvider;

use function Xray\Tests\invade;

pest()->group('providers');
covers(AzureStorageServiceProvider::class);

it('should register the Azure Storage service provider', function () {
    config()->set('filesystems.disks.azure', $config = [
        'driver'      => 'azure',
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
    ]);

    /** @var FilesystemAdapter $disk */
    $disk = Storage::disk('azure');

    expect($disk)
        ->toBeInstanceOf(FilesystemAdapter::class)
        ->and($adapter = $disk->getAdapter())
        ->toBeInstanceOf(BlobStorageAdapter::class)
        ->toBe(invade('adapter', $disk->getDriver()));

    /** @var BlobStorageAdapter $adapter */
    expect(invade('container', $adapter))
        ->toBe($config['container']);

    expect($config)
        ->toBe(invade('config.options', $disk))
        ->toBe(invade('config.options', $disk->getDriver()));
});
