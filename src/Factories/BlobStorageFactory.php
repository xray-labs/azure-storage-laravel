<?php

declare(strict_types=1);

namespace Xray\AzureStorageLaravel\Factories;

use Xray\AzureStoragePhpSdk\BlobStorage\Concerns\ValidateContainerName;
use Xray\AzureStoragePhpSdk\BlobStorage\{BlobStorageClient, Config};
use Xray\AzureStoragePhpSdk\Contracts\Authentication\Auth;
use Xray\AzureStoragePhpSdk\Contracts\Http\Request as RequestContract;
use Xray\AzureStoragePhpSdk\Http\Request;

class BlobStorageFactory
{
    use ValidateContainerName;

    public function __construct(protected array $config)
    {
        //
    }

    public function createClient(): BlobStorageClient
    {
        $auth    = $this->getAuthenticationProvider();
        $request = $this->getRequestProvider($auth, new Config());

        return new BlobStorageClient($request);
    }

    public function getContainer(): string
    {
        $container = (string)($this->config['container'] ?? '');

        $this->validateContainerName($container);

        return $container;
    }

    protected function getAuthenticationProvider(): Auth
    {
        $provider = $this->config['authentication_provider'] ?? '';

        assert(
            !class_exists($provider) || !in_array(Auth::class, class_implements($provider)),
            'The authentication provider must implement the Auth interface.',
        );

        return new $provider($this->config);
    }

    protected function getRequestProvider(Auth $auth, Config $config): RequestContract
    {
        $protocol = ($this->config['secure'] ?? true) ? 'https' : 'http';
        $domain   = $this->config['url'] ?? null;

        return new Request($auth, $config, protocol: $protocol, domain: $domain);
    }
}
