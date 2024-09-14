<?php

declare(strict_types=1);

use Xray\AzureStorageLaravel\Exceptions\InvalidArgumentException;

pest()->group('exceptions');
covers(InvalidArgumentException::class);

it('should create a new instance of the exception', function () {
    $exception = InvalidArgumentException::make('The authentication provider must implement the Auth interface.');

    expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    expect($exception->getMessage())->toBe('The authentication provider must implement the Auth interface.');
});
