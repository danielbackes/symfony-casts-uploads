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

class ArticleReferenceAdminController extends BaseController
{

    /**
     * @Route("/admin/article/{id}/references", name="admin_article_add_reference", methods={"POST"})
     * @IsGranted("MANAGE", subject="article")
     */
    public function uploadArticleReference(Article $article, Request $request, UploaderHelper $uploaderHelper, EntityManagerInterface $entityManager)
    {
      /** @var UploadedFile $uploadedFile */
      $uploadedFile = $request->files->get('reference');

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