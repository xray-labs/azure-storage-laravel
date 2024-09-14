# Azure Storage PHP SDK

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

[![PHP CI](https://github.com/xray-labs/azure-storage-php-sdk/actions/workflows/CI.yaml/badge.svg)](https://github.com/xray-labs/azure-storage-php-sdk/actions/workflows/CI.yaml)

## Description

Laravel adapter for Microsoft Azure Blob Storage

## Installation

```bash
composer require xray/azure-storage-laravel
```

## Configuration

Add these new settings to your `filesystem.php` file

```php
'disks' => [
    ...

    'azure' => [
        'driver'      => 'azure',
        'account'     => env('AZURE_STORAGE_ACCOUNT'),
        'directory'   => env('AZURE_STORAGE_DIRECTORY'),
        'application' => env('AZURE_STORAGE_APPLICATION'),
        'secret'      => env('AZURE_STORAGE_SECRET'),
        'container'   => env('AZURE_STORAGE_CONTAINER'),
        // Optional parameters
        'options'     => [
            'authentication' => Xray\AzureStoragePhpSdk\Authentication\MicrosoftEntraId::class,
            'url'            => env('AZURE_STORAGE_URL'),
            'secure'         => env('AZURE_STORAGE_SECURE', true),
        ],
    ],
],
```

## License

This project is licensed under the [MIT License](LICENSE).

## Contacts

- sjpereira2000@gmail.com
- gabrielramos791@gmail.com
