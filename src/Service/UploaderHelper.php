<?php

namespace App\Service;

use Gedmo\Sluggable\Util\Urlizer;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\HttpFoundation\File\File;

class UploaderHelper
{
    const ARTICLE_IMAGE = 'article_image';
    const ARTICLE_REFERENCE = 'article_reference';

    private $publicFilesystem;
    private $privateFilesystem;
    private $requestStackContext;
    private $logger;
    private $publicAssetsBaseUrl;

    public function __construct(FilesystemInterface $publicUploadFilesystem, FilesystemInterface $privateUploadFilesystem, RequestStackContext $requestStackContext, LoggerInterface $logger, string $uploadedAssetsBaseUrl)
    {
        $this->publicFilesystem = $publicUploadFilesystem;
        $this->privateFilesystem = $privateUploadFilesystem;
        $this->requestStackContext = $requestStackContext;
        $this->logger = $logger;
        $this->publicAssetsBaseUrl = $uploadedAssetsBaseUrl;
    }
    
    public function uploadArticleImage(File $file, ?string $existingFilename): string
    {
       $newFilename = $this->uploadFile($file, self::ARTICLE_IMAGE, true);

        if ($existingFilename) {
            try {
                $result = $this->publicFilesystem->delete(self::ARTICLE_IMAGE.'/'.$existingFilename);

                if ($result === false) {
                    throw new \Exception(sprintf('Could not delete old uploaded file "%s"', $existingFilename));
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert(sprintf('Old uploaded file "%s" was missing when trying to delete', $existingFilename));
            }
        }

        return $newFilename;
    }

    public function uploadArticleReference(File $file): string
    {
        return $this->uploadFile($file, self::ARTICLE_REFERENCE, false);
    }

    public function getPublicPath(string $path): string
    {
        return $this->requestStackContext
            ->getBasePath().$this->publicAssetsBaseUrl.'/'.$path;
    }

    private function uploadFile(File $file, string $directory, bool $isPublic): string
    {
        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
        } else {
            $originalFilename = $file->getFilename();
        }

        $newFilename = Urlizer::urlize(pathinfo($originalFilename, PATHINFO_FILENAME)) . '-' . uniqid() . '.' . $file->guessExtension();
        
        $filesystem = $isPublic ? $this->publicFilesystem : $this->privateFilesystem;

        $stream = fopen($file->getPathname(), 'r');
        $result = $filesystem->writeStream(
            $directory.'/'.$newFilename,
            $stream
        );

        if ($result === false) {
            throw new \Exception(sprintf('Could not write uploaded file "%s"', $newFilename));
        }
      
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $newFilename;
    }
}