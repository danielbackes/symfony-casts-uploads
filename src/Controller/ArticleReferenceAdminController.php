<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleReference;
use App\Service\UploaderHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ArticleReferenceAdminController extends BaseController
{

    /**
     * @Route("/admin/article/{id}/references", name="admin_article_add_reference", methods={"POST"})
     * @IsGranted("MANAGE", subject="article")
     */
    public function uploadArticleReference(Article $article, Request $request, UploaderHelper $uploaderHelper, EntityManagerInterface $entityManager, ValidatorInterface $valitor)
    {
      /** @var UploadedFile $uploadedFile */
      $uploadedFile = $request->files->get('reference');

      $violations = $valitor->validate(
        $uploadedFile,
        [
          new NotBlank([
            'message' => 'Please select a file to upload'
          ]),
          new File([
            'maxSize' => '5M',
            'mimeTypes' => [
              'image/*',
              'application/pdf',
              'application/msword',
              'application/vnd.ms-excel',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'application/vnd.openxmlformats-officedocument.presentationml.presentation',
              'text/plain'
            ]
          ])
        ]
      );

      if ($violations->count() > 0) {
        return $this->json($violations, 400);
      }

      $filename = $uploaderHelper->uploadArticleReference($uploadedFile);

      $articleReference = new ArticleReference($article);
      $articleReference->setFilename($filename);
      $articleReference->setOriginalFilename($uploadedFile->getClientOriginalName() ?? $filename);
      $articleReference->setMimeType($uploadedFile->getMimeType() ?? 'aplication/octet-stream');

      $entityManager->persist($articleReference);
      $entityManager->flush();

      return $this->json(
        $articleReference,
        201, 
        [],
        [
          'groups' => ['main']
        ]
      );
    }

    /**
     * @Route("/admin/article/{id}/references", methods="GET", name="admin_article_list_references")
     * @IsGranted("MANAGE", subject="article")
     */
    public function getArticleReferences(Article $article)
    {
      return $this->json(
        $article->getArticleReferences(),
        200,
        [],
        [
            'groups' => ['main']
        ]
      );
    }

    /**
     * @Route("/admin/article/references/{id}/download", name="admin_article_download_reference", methods={"GET"})
     */
    public function downloadArticleReference(ArticleReference $reference, UploaderHelper $uploaderHelper)
    {
      $article = $reference->getArticle();
      $this->denyAccessUnlessGranted('MANAGE', $article);

      $response = new StreamedResponse(function() use ($reference, $uploaderHelper) {
        $outputStream = fopen('php://output', 'wb');
        $fileStream = $uploaderHelper->readStream($reference->getFilePath(), false);

        stream_copy_to_stream($fileStream, $outputStream);
      });

      $response->headers->set('Content-Type', $reference->getMimeType());

      $disposition = HeaderUtils::makeDisposition(
        HeaderUtils::DISPOSITION_ATTACHMENT,
        $reference->getOriginalFilename()
      );
      $response->headers->set('Content-Disposition', $disposition);

      return $response;
    }

    /**
     * @Route("/admin/article/references/{id}", name="admin_article_delete_reference", methods={"DELETE"})
     */
    public function deleteArticleReference(ArticleReference $reference, UploaderHelper $uploaderHelper, EntityManagerInterface $entityManager)
    {
        $article = $reference->getArticle();
        $this->denyAccessUnlessGranted('MANAGE', $article);

        $entityManager->remove($reference);
        $entityManager->flush();

        $uploaderHelper->deleteFile($reference->getFilePath(), false);

        return new Response(null, 204);
    }
}