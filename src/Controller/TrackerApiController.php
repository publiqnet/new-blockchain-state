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
     * @Route("/search/{word}", methods={"POST"})
     * @SWG\Post(
     *     summary="Search for Single Article / Author Articles",
     *     consumes={"application/json"}
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tracker / Search")
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
            $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['trackerContentUnitLight', 'trackerAccountLight', 'trackerFile']]);

            return new JsonResponse(['type' => 'author', 'data' => $articles]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
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
     */
    public function content(Request $request, string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger)
    {
        $em = $this->getDoctrine()->getManager();
        $channelAddress = $this->getParameter('channel_address');
        $removeFilesFromResponse = true;

        $contentUnit = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get user info & determine if view must be added
        $addView = $customService->viewLog($request, $contentUnit);

        if (1) {
            $removeFilesFromResponse = false;

            //  get files & find storage address
            $files = $contentUnit->getFiles();
            $contentUnitUri = $contentUnit->getUri();
            if ($files) {
                $fileStorageUrls = [];

                /**
                 * @var File $file
                 */
                foreach ($files as $file) {
                    /**
                     * @var Account[] $fileStorages
                     */
                    $fileStorages = $customService->getFileStoragesWithPublicAccess($file);
                    if (count($fileStorages)) {
                        $randomStorage = rand(0, count($fileStorages) - 1);
                        $storageUrl = $fileStorages[$randomStorage]->getUrl();
                        $storageAddress = $fileStorages[$randomStorage]->getPublicKey();
                        $fileUrl = $storageUrl . '/storage?file=' . $file->getUri() . '&channel_address=' . $channelAddress;

                        $file->setUrl($fileUrl);

                        $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'address' => $storageAddress];
                    } elseif ($contentUnit->getContent()) {
                        /**
                         * @var Content $content
                         */
                        $content = $contentUnit->getContent();

                        /**
                         * @var Account $channel
                         */
                        $channel = $content->getChannel();

                        $storageUrl = $channel->getUrl();
                        $storageAddress = $channel->getPublicKey();
                        $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                        $file->setUrl($fileUrl);

                        $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'address' => $storageAddress];
                    } else {
                        $fileStorageUrls[$file->getUri()] = ['url' => '', 'address' => ''];
                    }
                }

                //  replace file uri with url
                try {
                    foreach ($fileStorageUrls as $uri => $fileStorageData) {
                        $contentUnitText = $contentUnit->getText();
                        $contentUnitText = str_replace('src="' . $uri . '"', 'src="' . $fileStorageData['url'] . '"', $contentUnitText);
                        $contentUnit->setText($contentUnitText);

                        //  inform Blockchain about served files
//                        $blockChain->servedFile($uri, $contentUnitUri, $fileStorageData['address']);
                    }
                } catch (Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
        } else {
            $contentUnit->setText($contentUnit->getTextWithData());

            if ($contentUnit->getCover()) {
                /**
                 * @var File $file
                 */
                $file = $contentUnit->getCover();

                /**
                 * @var Account[] $fileStorages
                 */
                $fileStorages = $customService->getFileStoragesWithPublicAccess($file);
                if (count($fileStorages)) {
                    $randomStorage = rand(0, count($fileStorages) - 1);
                    $storageUrl = $fileStorages[$randomStorage]->getUrl();
                    $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                    $file->setUrl($fileUrl);
                } elseif ($contentUnit->getContent()) {
                    /**
                     * @var Content $content
                     */
                    $content = $contentUnit->getContent();

                    /**
                     * @var Account $channel
                     */
                    $channel = $content->getChannel();
                    $storageUrl = $channel->getUrl();
                    $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                    $file->setUrl($fileUrl);
                }
            }
        }

        /**
         * @var Transaction $transaction
         */
        $transaction = $contentUnit->getTransaction();
        $contentUnit->setPublished($transaction->getTimeSigned());

        //  check if transaction confirmed
        /**
         * @var Transaction $transaction
         */
        $transaction = $contentUnit->getTransaction();
        if ($transaction->getBlock()) {
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

        $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['trackerContentUnit', 'trackerAccountLight', 'trackerFile']]);

        //  remove files field if served from local
        if ($removeFilesFromResponse) {
            unset($contentUnit['files']);
        }

        return new JsonResponse($contentUnit);
    }
}