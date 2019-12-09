<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/4/19
 * Time: 1:38 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\BoostedContentUnit;
use App\Entity\Content;
use App\Entity\ContentUnit;
use App\Entity\ContentUnitViews;
use App\Entity\File;
use App\Entity\Transaction;
use App\Service\BlockChain;
use App\Service\Custom;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TrackerApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/tracker")
 */
class TrackerApiController extends Controller
{
    /**
     * @Route("/search/{word}/{count}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Search for Single Article / Author Articles",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tracker / Search")
     * @param string $word
     * @param int $count
     * @param $fromUri
     * @param Custom $customService
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function search(string $word, int $count, $fromUri, Custom $customService)
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
                     * @var Content $content
                     */
                    $content = $article->getContent();

                    /**
                     * @var Account $channel
                     */
                    $channel = $content->getChannel();
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
            $article = $this->get('serializer')->normalize($article, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);

            return new JsonResponse(['type' => 'article', 'data' => $article, 'more' => false]);
        }

        //  SEARCH IN AUTHORS
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $word]);
        if ($author) {
            $fromArticle = null;
            if ($fromUri) {
                $fromArticle = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $fromUri]);
            }

            /**
             * @var ContentUnit[] $articles
             */
            $articles = $em->getRepository(ContentUnit::class)->getAuthorArticles($author, $count + 1, $fromArticle);
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
            $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);

            $more = false;
            if (count($articles) > $count) {
                unset($articles[$count]);
                $more = true;
            }

            return new JsonResponse(['type' => 'author', 'data' => $articles, 'more' => $more]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    /**
     * @Route("/contents/{count}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get contents",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tracker / Content")
     * @param int $count
     * @param string $fromUri
     * @param Custom $customService
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function contents(int $count, string $fromUri, Custom $customService)
    {
        $em = $this->getDoctrine()->getManager();

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }

        /**
         * @var ContentUnit[] $articles
         */
        $articles = $em->getRepository(ContentUnit::class)->getArticles($count + 1, $fromContentUnit);

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
        $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);

        //  check if more content exist
        $more = false;
        if (count($articles) > $count) {
            unset($articles[$count]);
            $more = true;
        }

        return new JsonResponse(['data' => $articles, 'more' => $more]);
    }

    /**
     * @Route("/content/{uri}", methods={"GET"}, name="get_content_by_uri")
     * @SWG\Get(
     *     summary="Get content by uri",
     *     consumes={"application/json"},
     *     produces={"application/json"}
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tracker / Content")
     * @param Request $request
     * @param string $uri
     * @param Blockchain $blockChain
     * @param Custom $customService
     * @param LoggerInterface $logger
     * @return JsonResponse
     * @throws Exception
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function content(Request $request, string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger)
    {
        $em = $this->getDoctrine()->getManager();

        $contentUnit = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get user info & determine if view must be added
        $userIdentifier = $customService->viewLog($request, $contentUnit);

        //  get files & find storage address
        $files = $contentUnit->getFiles();
        $contentUnitUri = $contentUnit->getUri();
        if ($files) {
            $fileStorageUrls = [];

            /**
             * @var File $file
             */
            foreach ($files as $file) {
                $storageOrderToken = '';

                /**
                 * @var Account $fileStorage
                 */
                $fileStorage = $customService->getRandomFileStorage($file);
                if ($fileStorage) {
                    $storageUrl = $fileStorage->getUrl();
                    $storageAddress = $fileStorage->getPublicKey();

                    $storageOrder = $blockChain->getStorageOrder($storageAddress, $file->getUri(), $contentUnitUri, $userIdentifier);
                    if ($storageOrder['code']) {
                        $storageOrderToken = $storageOrder['storage_order'];
                        $storageOrderAddress = $storageOrder['storage_address'];
                        if ($storageOrderAddress != $storageAddress) {
                            $storageOrderAccount = $em->getRepository(Account::class)->findOneBy(['publicKey' => $storageOrderAddress]);
                            $storageUrl = $storageOrderAccount->getUrl();
                        }

                        $fileUrl = $storageUrl . '/storage?storage_order_token=' . $storageOrderToken;
                        $file->setUrl($fileUrl);

                        $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'storageOrderToken' => $storageOrderToken];
                    }
                }

                if (!$storageOrderToken && $contentUnit->getContent()) {
                    /**
                     * @var Account $channel
                     */
                    $channel = $contentUnit->getChannel();

                    $storageUrl = $channel->getUrl();
                    $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                    $file->setUrl($fileUrl);

                    $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'storageOrderToken' => ''];
                }
            }

            //  replace file uri with url
            try {
                foreach ($fileStorageUrls as $uri => $fileStorageData) {
                    $contentUnitText = $contentUnit->getText();
                    $contentUnitText = str_replace('src="' . $uri . '"', 'src="' . $fileStorageData['url'] . '"', $contentUnitText);
                    $contentUnit->setText($contentUnitText);

                    //  inform Blockchain about served files
                    if ($fileStorageData['storageOrderToken']) {
                        $blockChain->servedFile($fileStorageData['storageOrderToken']);
                    }
                }
            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }
        }

        /**
         * @var Transaction $transactionContentUnit
         */
        $transactionContentUnit = $contentUnit->getTransaction();
        $contentUnit->setPublished($transactionContentUnit->getTimeSigned());

        //  check if transaction confirmed
        if ($transactionContentUnit->getBlock()) {
            $contentUnit->setStatus('confirmed');
        } else {
            $contentUnit->setStatus('pending');
        }

        //  get article next & previous versions
        $previousVersions = $em->getRepository(ContentUnit::class)->getArticleHistory($contentUnit, true);
        if ($previousVersions) {
            /**
             * @var \App\Entity\ContentUnit $previousVersion
             */
            foreach ($previousVersions as $previousVersion) {
                /**
                 * @var Transaction $transaction
                 */
                $transaction = $previousVersion->getTransaction();
                $previousVersion->setPublished($transaction->getTimeSigned());
            }

            $previousVersions = $this->get('serializer')->normalize($previousVersions, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);
            $contentUnit->setPreviousVersions($previousVersions);
        }

        $nextVersions = $em->getRepository(ContentUnit::class)->getArticleHistory($contentUnit, false);
        if ($nextVersions) {
            /**
             * @var \App\Entity\ContentUnit $nextVersion
             */
            foreach ($nextVersions as $nextVersion) {
                /**
                 * @var Transaction $transaction
                 */
                $transaction = $nextVersion->getTransaction();
                $nextVersion->setPublished($transaction->getTimeSigned());
            }

            $nextVersions = $this->get('serializer')->normalize($nextVersions, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);
            $contentUnit->setNextVersions($nextVersions);
        }

        //  check if article boosted
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
        $contentUnit->setBoosted($isBoosted);

        //  get views by channel
        $viewsPerChannel = $em->getRepository(ContentUnitViews::class)->getArticleViewsPerChannel($contentUnit);

        $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['trackerContentUnit', 'trackerAccountLight', 'trackerFile']]);
        $contentUnit['viewsPerChannel'] = $viewsPerChannel;

        //  add transaction and block into return data
        $contentUnit['transactionHash'] = $transactionContentUnit->getTransactionHash();
        if ($transactionContentUnit->getBlock()) {
            $contentUnit['blockHash'] = $transactionContentUnit->getBlock()->getHash();
        } else {
            $contentUnit['blockHash'] = null;
        }

        return new JsonResponse($contentUnit);
    }
}