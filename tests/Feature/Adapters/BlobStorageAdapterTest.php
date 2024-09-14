<?php

declare(strict_types=1);

use League\Flysystem\{Config, FileAttributes, UnableToCheckExistence, UnableToCopyFile, UnableToCreateDirectory, UnableToDeleteDirectory, UnableToDeleteFile, UnableToListContents, UnableToMoveFile, UnableToReadFile, UnableToRetrieveMetadata, UnableToSetVisibility, UnableToWriteFile, Visibility};
use Pest\Expectation;

use function Pest\Laravel\{freezeTime, mock};

use Xray\AzureStorageLaravel\Adapters\BlobStorageAdapter;
use Xray\AzureStoragePhpSdk\Authentication\MicrosoftEntraId;
use Xray\AzureStoragePhpSdk\BlobStorage\Entities\Blob\{Blobs};
use Xray\AzureStoragePhpSdk\BlobStorage\Entities\Container\ContainerProperties;
use Xray\AzureStoragePhpSdk\BlobStorage\Managers\Blob\BlobManager;
use Xray\AzureStoragePhpSdk\BlobStorage\Managers\ContainerManager;
use Xray\AzureStoragePhpSdk\BlobStorage\Resources\File;
use Xray\AzureStoragePhpSdk\BlobStorage\{BlobStorageClient, Resource as XrayResource};
use Xray\AzureStoragePhpSdk\Exceptions\RequestException;
use Xray\AzureStoragePhpSdk\Fakes\Http\{RequestFake};

pest()->group('adapters');
covers(BlobStorageAdapter::class);

it('should create a new instance of the adapter', function () {
    expect(createAdapterToBlobStorageAdapterTest())
        ->toBeInstanceOf(BlobStorageAdapter::class);
});

it('should return the correct url', function () {
    $adapter = createAdapterToBlobStorageAdapterTest();

    expect($adapter->url('file.txt'))
        ->toBeString()
        ->toBe('http://account.microsoft.azure/container/file.txt?');
});

it('should determine if temporary URLs can be generated', function () {
    $adapter = createAdapterToBlobStorageAdapterTest();

    expect($adapter->providesTemporaryUrls())
        ->toBeTrue();
});

it('should generate a temporary URL', function (string $method, string|array $expected) {
    freezeTime();

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('temporaryUrl')
        ->once()
        ->withArgs(
            fn ($path, $expiration) => $path === 'file.txt'
            && now()->addMinutes(5)->equalTo($expiration)
        )
        ->andReturn('http://assigned_url')
        ->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    expect($adapter->{$method}('file.txt', now()->addMinutes(5)))
        ->toBe($expected);
})->with([
    'For the given path'        => ['temporaryUrl', 'http://assigned_url'],
    'For the given upload path' => ['temporaryUploadUrl', ['url' => 'http://assigned_url', 'headers' => []]],
]);

it('should check if file exists', function (bool $fileExists) {
    freezeTime();

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('get')
        ->once()
        ->with($path = 'file.txt')
        ->andReturn(new File('file.txt'));

    if (!$fileExists) {
        $manager->andThrow(RequestException::createFromMessage('File not found'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    expect($adapter->fileExists($path))
        ->toBe($fileExists);
})->with([
    'File exists'         => [true],
    'File does not exist' => [false],
]);

it('should validate unavailable methods', function (string $method, string $exception, array $args) {
    $adapter = createAdapterToBlobStorageAdapterTest();

    expect(fn () => $adapter->{$method}(...$args))
        ->toThrow($exception);
})->with([
    'Directory Exists' => ['directoryExists', UnableToCheckExistence::class, ['path']],
    'Delete Directory' => ['deleteDirectory', UnableToDeleteDirectory::class, ['path']],
    'Create Directory' => ['createDirectory', UnableToCreateDirectory::class, ['path', new Config()]],
    'Set Visibility'   => ['setVisibility', UnableToSetVisibility::class, ['path', 'public']],
]);

it('should write contents', function (string $method, mixed $contents, bool $throws) {
    freezeTime();

    $path = 'path/file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('putBlock')
        ->once()
        ->withArgs(fn (File $file) => $file->getFilename() === $path
            && $file->getContent() === 'contents')
        ->andReturn(true);

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to write file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($contents instanceof Closure) {
        $contents = $contents();
    }

    if ($throws) {
        expect(fn () => ($adapter->{$method}($path, $contents, new Config())))
            ->toThrow(UnableToWriteFile::class);
    } else {
        expect($adapter->{$method}($path, $contents, new Config()))
            ->toBeNull();
    }
})->with([
    'Write contents' => ['write', 'contents'],
    'Write stream'   => ['writeStream', function () {
        /** @var resource $stream */
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, 'contents');
        rewind($stream);

        return $stream;
    }],
])->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should read contents', function (string $method, bool $isResource, bool $throws) {
    freezeTime();

    $path = 'path/file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('get')
        ->once()
        ->with($path)
        ->andReturn(new File($path, 'contents'));

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to read file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => ($adapter->{$method}($path)))
            ->toThrow(UnableToReadFile::class);
    } else {
        $result  = $adapter->{$method}($path);
        $content = $isResource ? stream_get_contents($result) : $result;

        expect($content)
            ->toBe('contents');
    }
})->with([
    'Read contents' => ['read', false],
    'Read stream'   => ['readStream', true],
])->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should delete a file', function (bool $throws) {
    freezeTime();

    $path = 'path/file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('delete')
        ->once()
        ->with($path, null, true)
        ->andReturn(true);

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to delete file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => ($adapter->delete($path)))
            ->toThrow(UnableToDeleteFile::class);
    } else {
        $adapter->delete($path);

        expect()->not->toThrow(UnableToDeleteFile::class);
    }
})->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should get visibility', function (string $visibility, bool $throws) {
    freezeTime();

    // @phpstan-ignore-next-line
    $manager = mock(ContainerManager::class)
        ->shouldReceive('getProperties')
        ->once()
        ->with('container')
        ->andReturn(new ContainerProperties(
            $visibility === Visibility::PUBLIC
            ? [XrayResource::BLOB_PUBLIC_ACCESS => 'blob']
            : []
        ));

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to read file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('containers')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => ($adapter->visibility('file.txt')))
            ->toThrow(UnableToRetrieveMetadata::class);
    } else {
        $result = $adapter->visibility('file.txt');

        expect($result)
            ->toBeInstanceOf(FileAttributes::class);

        expect($result->visibility())
            ->toBe($visibility);
    }
})->with([
    'Public visibility'  => [Visibility::PUBLIC],
    'Private visibility' => [Visibility::PRIVATE],
])->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should get file attributes', function (string $method, bool $throws) {
    freezeTime();

    $path = 'file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('get')
        ->once()
        ->with($path)
        ->andReturn(new File($path, 'content'));

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to read file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => ($adapter->{$method}('file.txt')))
            ->toThrow(UnableToRetrieveMetadata::class);
    } else {
        $result = $adapter->{$method}('file.txt');

        expect($result)
            ->toBeInstanceOf(FileAttributes::class);
    }
})->with([
    'Mime Type'     => ['mimeType'],
    'Last Modified' => ['lastModified'],
    'File Size'     => ['fileSize'],
])->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should list contents', function (bool $withExceptionWhenListingFiles, bool $withExceptionWhenGettingFile) {
    $path = 'path/';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('get')
        ->times(match (true) {
            !$withExceptionWhenListingFiles && !$withExceptionWhenGettingFile => 2,
            $withExceptionWhenListingFiles                                    => 0,
            default                                                           => 1,
        })
        ->andReturn(new File('path/file.txt'), new File('path/directory'));

    if ($withExceptionWhenGettingFile) {
        $manager->andThrow(RequestException::createFromMessage('Unable to read file'));
    }

    /** @var BlobManager $mockedManager */
    $mockedManager = $manager->getMock();

    $manager = $manager->getMock()
        ->shouldReceive('list')
        ->once()
        ->with(['prefix' => $path])
        ->andReturn(new Blobs($mockedManager, [
            ['Name' => 'path/file.txt'],
            ['Name' => 'path/directory'],
        ]));

    if ($withExceptionWhenListingFiles) {
        $manager->andThrow(RequestException::createFromMessage('Unable to list files'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($withExceptionWhenListingFiles || $withExceptionWhenGettingFile) {
        expect(fn () => iterator_to_array($adapter->listContents($path, false)))
            ->toThrow(
                $withExceptionWhenListingFiles
                    ? UnableToListContents::class
                    : UnableToReadFile::class
            );
    } else {
        $result = $adapter->listContents($path, false);

        expect(iterator_to_array($result))
            ->toHaveCount(2)
            ->each(function (Expectation $value) {
                $value->toBeInstanceOf(FileAttributes::class);
            });
    }
})->with([
    'Without exceptions'                => [false, false],
    'With exception when listing files' => [true, false],
    'With exception when getting file'  => [false, true],
]);

it('should copy a file', function (bool $throws) {
    $source      = 'source/file.txt';
    $destination = 'destination/file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('copy')
        ->once()
        ->with($source, $destination)
        ->andReturn(true);

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to copy file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => $adapter->copy($source, $destination, new Config()))
            ->toThrow(UnableToCopyFile::class);
    } else {
        $adapter->copy($source, $destination, new Config());

        expect(true)->toBeTrue();
    }
})->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should move a file', function (bool $throws) {
    $source      = 'source/file.txt';
    $destination = 'destination/file.txt';

    // @phpstan-ignore-next-line
    $manager = mock(BlobManager::class)
        ->shouldReceive('copy')
        ->once()
        ->with($source, $destination)
        ->andReturn(true)
        ->getMock()
        ->shouldReceive('delete')
        ->once()
        ->with($source, null, true)
        ->andReturn(true);

    if ($throws) {
        $manager->andThrow(RequestException::createFromMessage('Unable to move file'));
    }

    $manager = $manager->getMock();

    /** @var BlobStorageClient $client */
    $client = mock(BlobStorageClient::class) // @phpstan-ignore-line
        ->shouldReceive('blobs')
        ->once()
        ->andReturn($manager)
        ->getMock();

    $adapter = new BlobStorageAdapter($client, 'container');

    if ($throws) {
        expect(fn () => $adapter->move($source, $destination, new Config()))
            ->toThrow(UnableToMoveFile::class);
    } else {
        $adapter->move($source, $destination, new Config());

        expect(true)->toBeTrue();
    }
})->with([
    'Without exception' => [false],
    'With exception'    => [true],
]);

it('should get client', function () {
    $adapter = createAdapterToBlobStorageAdapterTest();

    expect($adapter->getClient())
        ->toBeInstanceOf(BlobStorageClient::class);
});

it('should create file attributes', function (bool $allItems) {
    freezeTime();

    $adapter = createAdapterToBlobStorageAdapterTest();

    $attributes = (function () use ($allItems) {
        return $this->createFileAttributes('file.txt', new File('file.txt', 'content', [
            'Content-Length'     => '7',
            'Content-Type'       => 'text/plain',
            'Content-MD5'        => $allItems ? 'Y29udGVudA==' : '',
            'Last-Modified'      => '2021-10-01T00:00:00Z',
            'x-ms-creation-time' => '2021-10-01T00:00:00Z',
        ]));
    })->call($adapter);

    // @phpstan-ignore-next-line
    expect($attributes)
        ->toBeInstanceOf(FileAttributes::class)
        ->extraMetadata()
        ->toBe($allItems ? [
            'contentMd5'   => 'Y29udGVudA==',
            'creationTime' => strtotime('2021-10-01T00:00:00Z'),
        ] : [
            'creationTime' => strtotime('2021-10-01T00:00:00Z'),
        ])
        ->mimeType()->toBe('text/plain')
        ->fileSize()->toBe(7)
        ->lastModified()->toBe(strtotime('2021-10-01T00:00:00Z'))
        ->path()->toBe('file.txt')
        ->visibility()->toBeNull();
})->with([
    'All attributes'     => [true],
    'Missing attributes' => [false],
]);

function createAdapterToBlobStorageAdapterTest(): BlobStorageAdapter
{
    $auth = new MicrosoftEntraId([
        'account'     => 'account',
        'directory'   => 'directory',
        'application' => 'application',
        'secret'      => 'secret',
    ]);

    $request = new RequestFake($auth);

    $client = new BlobStorageClient($request);

    return new BlobStorageAdapter($client, 'container');
}
