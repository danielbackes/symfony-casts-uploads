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
        /** @var ConstraintViolation $violation */
        $violation = $violations[0];

        $this->addFlash('error', $violation->getMessage());

        return $this->redirectToRoute('admin_article_edit', [
          'id' => $article->getId()
        ]);
      }

      $filename = $uploaderHelper->uploadArticleReference($uploadedFile);

      $articleReference = new ArticleReference($article);
      $articleReference->setFilename($filename);
      $articleReference->setOriginalFilename($uploadedFile->getClientOriginalName() ?? $filename);
      $articleReference->setMimeType($uploadedFile->getMimeType() ?? 'aplication/octet-stream');

      $entityManager->persist($articleReference);
      $entityManager->flush();

      return $this->redirectToRoute('admin_article_edit', [
        'id' => $article->getId()
      ]);
    }
}