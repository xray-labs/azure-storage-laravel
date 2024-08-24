<?php

declare(strict_types=1);

namespace Xray\AzureStorageLaravel\Adapters;

use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use League\Flysystem\{Config,
    FileAttributes,
    FilesystemAdapter,
    StorageAttributes,
    UnableToCheckExistence,
    UnableToCopyFile,
    UnableToCreateDirectory,
    UnableToDeleteDirectory,
    UnableToDeleteFile,
    UnableToListContents,
    UnableToMoveFile,
    UnableToReadFile,
    UnableToRetrieveMetadata,
    UnableToSetVisibility,
    UnableToWriteFile,
    Visibility};
use Xray\AzureStoragePhpSdk\BlobStorage\BlobStorageClient;
use Xray\AzureStoragePhpSdk\BlobStorage\Entities\Blob\Blob;
use Xray\AzureStoragePhpSdk\BlobStorage\Entities\Container\ContainerProperties;
use Xray\AzureStoragePhpSdk\BlobStorage\Resources\File;
use Xray\AzureStoragePhpSdk\Exceptions\Authentication\InvalidAuthenticationMethodException;
use Xray\AzureStoragePhpSdk\Exceptions\{InvalidArgumentException, InvalidResourceTypeException, RequestException};

class BlobStorageAdapter implements FilesystemAdapter
{
    use Conditionable;

    public function __construct(protected BlobStorageClient $client, protected string $container)
    {
        //
    }

    /**
     * Get the URL for the file at the given path.
     */
    public function url(string $path): string
    {
        $request = $this->getClient()->getRequest();

        return $request->uri("{$this->container}/{$path}");
    }

    /**
     * Determine if temporary URLs can be generated.
     */
    public function providesTemporaryUrls(): bool
    {
        return true;
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @throws InvalidArgumentException
     * @throws InvalidAuthenticationMethodException
     * @throws InvalidResourceTypeException
     *
     * @param array<string, mixed> $options
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        $manager = $this->getClient()->blobs($this->container);

        return $manager->temporaryUrl($path, $expiration);
    }

    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @throws InvalidArgumentException
     * @throws InvalidAuthenticationMethodException
     * @throws InvalidResourceTypeException
     *
     * @param array<string, mixed> $options
     * @return array{url: string, headers: array<string, string>}
     */
    public function temporaryUploadUrl(string $path, DateTimeInterface $expiration, array $options = []): array
    {
        $manager = $this->getClient()->blobs($this->container);
        $uri     = $manager->temporaryUrl($path, $expiration);

        return [
            'url'     => $uri,
            'headers' => [],
        ];
    }

    /**
     * Determine if the given path is a file.
     *
     * @throws UnableToCheckExistence
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the given path is a directory.
     *
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        throw new UnableToCheckExistence('Directory existence is not supported');
    }

    /**
     * Write a new file to the given path.
     *
     * @throws UnableToWriteFile
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $file = new File($path, $contents);

        try {
            $this->getClient()->blobs($this->container)->putBlock($file);
        } catch (RequestException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Write a new file using a stream.
     *
     * @param resource $contents
     *
     * @throws UnableToWriteFile
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string)stream_get_contents($contents), $config);
    }

    /**
     * Read a file from a given path.
     *
     * @throws UnableToReadFile
     */
    public function read(string $path): string
    {
        try {
            $file = $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        return $file->getContent();
    }

    /**
     * Read the contents of a file as a stream.
     *
     * @return resource
     *
     * @throws UnableToReadFile
     */
    public function readStream(string $path)
    {
        try {
            $file = $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        if (($stream = fopen('php://memory', 'r+')) === false) {
            throw UnableToReadFile::fromLocation($path, 'Failed to open stream');
        }

        fwrite($stream, $file->getContent());
        rewind($stream);

        return $stream;

    }

    /**
     * Delete the file at a given path.
     *
     * @throws UnableToDeleteFile
     */
    public function delete(string $path): void
    {
        try {
            $this->getClient()->blobs($this->container)->delete($path, force: true);
        } catch (RequestException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Delete the directory at a given path.
     *
     * @throws UnableToDeleteDirectory
     */
    public function deleteDirectory(string $path): void
    {
        throw new UnableToDeleteDirectory('Directory deletion is not supported');
    }

    /**
     * Create a directory.
     *
     * @throws UnableToCreateDirectory
     */
    public function createDirectory(string $path, Config $config): void
    {
        throw new UnableToCreateDirectory('Directory creation is not supported');
    }

    /**
     * Set the visibility for a given file or directory.
     *
     * @throws UnableToSetVisibility
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw new UnableToSetVisibility('Setting visibility is not supported');
    }

    /**
     * Get the visibility for the given path.
     *
     * @throws UnableToRetrieveMetadata
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $properties = $this->getClient()->containers()->getProperties($this->container);
        } catch (RequestException $e) {
            throw UnableToRetrieveMetadata::visibility($path, $e->getMessage(), $e);
        }

        return $this->createFileVisibility($path, $properties);
    }

    /**
     * Get the mime-type for the given path.
     *
     * @throws UnableToRetrieveMetadata
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $file = $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }

        return $this->createFileAttributes($path, $file);
    }

    /**
     * @throws UnableToRetrieveMetadata
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $file = $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }

        return $this->createFileAttributes($path, $file);
    }

    /**
     * @throws UnableToRetrieveMetadata
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $file = $this->getClient()->blobs($this->container)->get($path);
        } catch (RequestException $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }

        return $this->createFileAttributes($path, $file);
    }

    /**
     * @return iterable<StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $blobs = $this->getClient()->blobs($this->container)->list([
                'prefix' => Str::deduplicate("{$path}/", '/'),
            ]);
        } catch(RequestException $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }

        /** @var Blob $blob */
        foreach ($blobs as $blob) {
            try {
                yield $this->createFileAttributes($path, $blob->get());
            } catch (RequestException $e) {
                throw UnableToReadFile::fromLocation($blob->name, $e->getMessage(), $e);
            }
        }
    }

    /**
     * @throws UnableToMoveFile
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $manager = $this->getClient()->blobs($this->container);

        try {
            $manager->copy($source, $destination);
            $manager->delete($source, force: true);
        } catch (RequestException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * @throws UnableToCopyFile
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->getClient()->blobs($this->container)->copy($source, $destination);
        } catch (RequestException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
    * Get the BlobStorageClient
    *
    * @return BlobStorageClient
    */
    public function getClient(): BlobStorageClient
    {
        return $this->client;
    }

    protected function createFileAttributes(string $path, File $file): FileAttributes
    {
        $extraMetadata = array_filter([
            'contentMd5'   => $file->getContentMD5(),
            'creationTime' => $file->getCreationTime()->getTimestamp(),
        ]);

        return new FileAttributes(
            $path,
            fileSize: $file->getContentLength(),
            lastModified: $file->getLastModified()->getTimestamp(),
            mimeType: $file->getContentType(),
            extraMetadata: $extraMetadata,
        );
    }

    protected function createFileVisibility(string $path, ContainerProperties $properties): FileAttributes
    {
        $visibility = is_null($properties->blobPublicAccess)
            ? Visibility::PRIVATE
            : Visibility::PUBLIC;

        return new FileAttributes($path, visibility: $visibility);
    }
}
