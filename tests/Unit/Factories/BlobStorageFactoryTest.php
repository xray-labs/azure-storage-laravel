<?php

declare(strict_types=1);

use Xray\AzureStorageLaravel\Exceptions\InvalidArgumentException;
use Xray\AzureStorageLaravel\Factories\BlobStorageFactory;
use Xray\AzureStoragePhpSdk\Authentication\MicrosoftEntraId;
use Xray\AzureStoragePhpSdk\BlobStorage\{BlobStorageClient, Config};
use Xray\AzureStoragePhpSdk\Contracts\Authentication\Auth;
use Xray\AzureStoragePhpSdk\Contracts\Http\Request;

pest()->group('factories');
covers(BlobStorageFactory::class);

it('should create a new instance of the factory', function () {
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
    ]);

    expect($factory)->toBeInstanceOf(BlobStorageFactory::class);
});

it('should create client', function () {
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
    ]);

    $client = $factory->createClient();

    expect($client)->toBeInstanceOf(BlobStorageClient::class);
});

it('should get container name', function () {
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
    ]);

    expect($factory->getContainer())->toBe('your-container-name');
});

it('should get authentication provider', function (?string $authProvider) {
    // @phpstan-ignore-next-line
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
        'options'     => [
            'authentication' => $authProvider,
        ],
    ]);

    /** @var Auth $auth */
    $auth = (fn () => $this->getAuthenticationProvider())->call($factory);

    expect($auth)->toBeInstanceOf(MicrosoftEntraId::class);
})->with([
    'Default auth provider' => [null],
    'Custom auth provider'  => [MicrosoftEntraId::class],
]);

it('should throw an exception when the authentication provider does not implement the Auth interface', function () {
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
        'options'     => [
            'authentication' => 'InvalidAuthProvider',
        ],
    ]);

    (fn () => $this->getAuthenticationProvider())->call($factory);
})->throws(InvalidArgumentException::class, 'The authentication provider must implement the Auth interface.');

it('should get request provider', function (?bool $secure) {
    $factory = new BlobStorageFactory([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => 'your-container-name',
        'options'     => array_filter([
            'secure' => $secure,
            'url'    => 'your-custom-url',
        ], fn (mixed $value) => $value !== null),
    ]);

    /** @var Auth $auth */
    $auth = (fn () => $this->getAuthenticationProvider())->call($factory);

    $config = new Config();

    /** @var Request $request */
    $request = (fn () => $this->getRequestProvider($auth, $config))->call($factory);

    expect($request)->toBeInstanceOf(Request::class);

    // @phpstan-ignore-next-line
    expect((fn () => $this->protocol)->call($request))->toBe(($secure ?? true) ? 'https' : 'http');

    // @phpstan-ignore-next-line
    expect((fn () => $this->domain)->call($request))->toBe('your-custom-url');
})->with([
    'Secure protocol'   => [true],
    'Insecure protocol' => [false],
    'Default protocol'  => [null],
]);

it('should validate the container name', function (string|null|int $containerName, bool $toThrow) {
    // @phpstan-ignore-next-line
    $factory = new BlobStorageFactory(array_filter([
        'account'     => 'your-storage-account-name',
        'secret'      => 'your-storage-account-secret',
        'directory'   => 'your-directory-name',
        'application' => 'your-application-name',
        'container'   => $containerName,
    ], fn (mixed $value) => $value !== null));

    if ($toThrow) {
        expect(fn () => $factory->getContainer())
            ->toThrow(InvalidArgumentException::class, "Invalid container name: [{$containerName}]");

        return;
    }

    expect($factory->getContainer())->toBe((string) $containerName);
})->with([
    'Valid container name'   => ['your-container-name', false],
    'Invalid container name' => ['invalid container', true],
    'With numbers'           => [123456, false],
    'Default container name' => [null, true],
]);
