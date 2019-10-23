<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/28/19
 * Time: 12:05 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\BoostedContentUnit;
use App\Entity\ContentUnit;
use App\Entity\File;
use App\Entity\Transaction;
use App\Service\Custom;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SearchApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/search")
 */
class SearchApiController extends Controller
{
    /**
     * @Route("/{word}", methods={"POST"})
     * @SWG\Post(
     *     summary="Search for Single Article / Author Articles",
     *     consumes={"application/json"}
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Search")
     * @param string $word
     * @param Custom $customService
     * @return Response
     */
    public function search(string $word, Custom $customService)
    {
        $em = $this->getDoctrine()->getManager();

        //  SEARCH IN ARTICLES
        $article = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $word]);
        if ($article) {
            if ($article->getCover()) {
                /**
                 * @var File $file
                 */
                $file = $article->getCover();
                if ($article->getContent()) {
                    /**
                     * @var Account $channel
                     */
                    $channel = $article->getContent()->getChannel();
                    $storageUrl = $channel->getUrl();
                    $file->setUrl($storageUrl . '/storage?file=' . $file->getUri());
                } else {
                    /**
                     * @var Account[] $fileStorages
                     */
                    $fileStorages = $customService->getFileStoragesWithPublicAccess($file);
                    if (count($fileStorages)) {
                        $randomStorage = rand(0, count($fileStorages) - 1);
                        $storageUrl = $fileStorages[$randomStorage]->getUrl();
                        $file->setUrl($storageUrl . '/storage?file=' . $file->getUri());
                    }
                }
            }

            //  check if transaction confirmed
            /**
             * @var Transaction $transaction
             */
            $transaction = $article->getTransaction();
            if ($transaction->getBlock()) {
                $article->setStatus('confirmed');
            } else {
                $article->setStatus('pending');
            }

            $article->setPublished($transaction->getTimeSigned());

            //  check if article boosted
            $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($article);
            $article->setBoosted($isBoosted);

            //  generate short description
            $desc = $article->getTextWithData();
            $desc = trim(strip_tags($desc));
            if (strlen($desc) > 300) {
                $desc = substr($desc, 0, strpos($desc, ' ')) . '...';
            }
            $article->setDescription($desc);

            //  normalize to return
            $article = $this->get('serializer')->normalize($article, null, ['groups' => ['contentUnitList', 'file', 'accountBase']]);

            return new JsonResponse(['type' => 'article', 'data' => $article]);
        }

        //  SEARCH IN AUTHORS
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $word]);
        if ($author) {
            /**
             * @var ContentUnit[] $articles
             */
            $articles = $em->getRepository(ContentUnit::class)->getAuthorArticles($author, 9999);
            if ($articles) {
                foreach ($articles as $article) {
                    if ($article->getCover()) {
                        /**
                         * @var File $file
                         */
                        $file = $article->getCover();
                        if ($article->getContent()) {
                            /**
                             * @var Account $channel
                             */
                            $channel = $article->getContent()->getChannel();
                            $storageUrl = $channel->getUrl();
                            $file->setUrl($storageUrl . '/storage?file=' . $file->getUri());
                        } else {
                            /**
                             * @var Account[] $fileStorages
                             */
                            $fileStorages = $customService->getFileStoragesWithPublicAccess($file);
                            if (count($fileStorages)) {
                                $randomStorage = rand(0, count($fileStorages) - 1);
                                $storageUrl = $fileStorages[$randomStorage]->getUrl();
                                $file->setUrl($storageUrl . '/storage?file=' . $file->getUri());
                            }
                        }
                    }

                    //  check if transaction confirmed
                    /**
                     * @var Transaction $transaction
                     */
                    $transaction = $article->getTransaction();
                    if ($transaction->getBlock()) {
                        $article->setStatus('confirmed');
                    } else {
                        $article->setStatus('pending');
                    }

                    $article->setPublished($transaction->getTimeSigned());

                    //  check if article boosted
                    $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($article);
                    $article->setBoosted($isBoosted);

                    //  generate short description
                    $desc = $article->getTextWithData();
                    $desc = trim(strip_tags($desc));
                    if (strlen($desc) > 300) {
                        $desc = substr($desc, 0, strpos($desc, ' ')) . '...';
                    }
                    $article->setDescription($desc);
                }
            }
            $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['contentUnitList', 'file', 'accountBase']]);

            return new JsonResponse(['type' => 'author', 'data' => $articles]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}