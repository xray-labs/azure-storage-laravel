<?php

declare(strict_types=1);

namespace Xray\AzureStorageLaravel\Factories;

use Xray\AzureStorageLaravel\Exceptions\InvalidArgumentException;
use Xray\AzureStoragePhpSdk\Authentication\MicrosoftEntraId;
use Xray\AzureStoragePhpSdk\BlobStorage\Concerns\ValidateContainerName;
use Xray\AzureStoragePhpSdk\BlobStorage\{BlobStorageClient, Config};
use Xray\AzureStoragePhpSdk\Contracts\Authentication\Auth;
use Xray\AzureStoragePhpSdk\Contracts\Http\Request as RequestContract;
use Xray\AzureStoragePhpSdk\Exceptions\InvalidArgumentException as XraySdkInvalidArgumentException;
use Xray\AzureStoragePhpSdk\Http\Request;

class BlobStorageFactory
{
    use ValidateContainerName;

    /** @param array{account: string, application: string, directory: string, secret: string, container?: string, options?: array{authentication?: string, url?: string, secure?: bool}} $config */
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

        try {
            $this->validateContainerName($container);
        } catch (XraySdkInvalidArgumentException) {
            throw InvalidArgumentException::make("Invalid container name: [{$container}]");
        }

        return $container;
    }

    protected function getAuthenticationProvider(): Auth
    {
        $provider = $this->config['options']['authentication'] ?? MicrosoftEntraId::class;

        if (!class_exists($provider) || !in_array(Auth::class, class_implements($provider))) {
            throw InvalidArgumentException::make('The authentication provider must implement the Auth interface.');
        }

        /** @var Auth $provider */
        return new $provider($this->config);
    }

    protected function getRequestProvider(Auth $auth, Config $config): RequestContract
    {
        $protocol = ($this->config['options']['secure'] ?? true) ? 'https' : 'http';
        $domain   = $this->config['options']['url'] ?? null;

        return new Request($auth, $config, protocol: $protocol, domain: $domain);
    }
}
