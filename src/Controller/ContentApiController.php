<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/18/19
 * Time: 12:13 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\BoostedContentUnit;
use App\Entity\File;
use App\Entity\Transaction;
use App\Service\BlockChain;
use App\Service\ContentUnit as CUService;
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
 * Class ContentApiController
 * @package App\Controller
 * @Route("/api/content")
 */
class ContentApiController extends Controller
{
    /**
     * @Route("s/{count}/{boostedCount}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get (user) contents",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param int $count
     * @param int $boostedCount
     * @param string $fromUri
     * @param CUService $contentUnitService
     * @return JsonResponse
     */
    public function contents(int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }

        if ($account) {
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorArticles($account, $count + 1, $fromContentUnit, true);
        } else {
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getArticles($count + 1, $fromContentUnit);
        }

        //  prepare data to return
        if ($contentUnits) {
            try {
                $contentUnits = $contentUnitService->prepare($contentUnits, null, $account);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $boostedContentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getBoostedArticles($boostedCount, $contentUnits);
        if ($boostedContentUnits) {
            try {
                $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        if ($account) {
            $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'previousVersions', 'boost', 'boostedContentUnitMain', 'transactionLight']]);
        } else {
            $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        }

        $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);

        //  check if more content exist
        $more = false;
        if (count($contentUnits) > $count) {
            unset($contentUnits[$count]);
            $more = true;
        }

        //  add boosted articles into random positions of main articles list
        for ($i = 0; $i < count($boostedContentUnits); $i++) {
            $aaa = [$boostedContentUnits[$i]];
            array_splice($contentUnits, rand(0, count($contentUnits) - 1), 0, $aaa);
        }

        return new JsonResponse(['data' => $contentUnits, 'more' => $more]);
    }

    /**
     * @Route("s/{publicKey}/{count}/{boostedCount}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get custom user contents",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param string $publicKey
     * @param int $count
     * @param int $boostedCount
     * @param string $fromUri
     * @param CUService $contentUnitService
     * @return JsonResponse
     */
    public function authorContents(string $publicKey, int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$account) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }
        $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorArticles($account, $count + 1, $fromContentUnit);

        //  prepare data to return
        if ($contentUnits) {
            try {
                $contentUnits = $contentUnitService->prepare($contentUnits);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $boostedContentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getBoostedArticles($boostedCount, $contentUnits);
        if ($boostedContentUnits) {
            try {
                $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);

        //  check if more content exist
        $more = false;
        if (count($contentUnits) > $count) {
            unset($contentUnits[$count]);
            $more = true;
        }

        //  add boosted articles into random positions of main articles list
        for ($i = 0; $i < count($boostedContentUnits); $i++) {
            $aaa = [$boostedContentUnits[$i]];
            array_splice($contentUnits, rand(0, count($contentUnits) - 1), 0, $aaa);
        }

        return new JsonResponse(['data' => $contentUnits, 'more' => $more]);
    }

    /**
     * @Route("/{uri}", methods={"GET"}, name="get_content_by_uri")
     * @SWG\Get(
     *     summary="Get content by uri",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param string $uri
     * @param Blockchain $blockChain
     * @param Custom $customService
     * @param LoggerInterface $logger
     * @param CUService $contentUnitService
     * @return JsonResponse
     * @throws Exception
     */
    public function content(Request $request, string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();
        $channelAddress = $this->getParameter('channel_address');
        $removeFilesFromResponse = true;

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get user info & determine if view must be added
        $addView = $customService->viewLog($request, $contentUnit, $account);

        //  if viewer is article author return full data without adding view
        if ($account && $contentUnit->getAuthor() == $account) {
            $removeFilesFromResponse = false;

            //  get files & find storage address
            $files = $contentUnit->getFiles();
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
                        $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                        $file->setUrl($fileUrl);

                        $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'address' => $storageAddress];
                    } elseif ($contentUnit->getContent()) {
                        /**
                         * @var Account $channel
                         */
                        $channel = $contentUnit->getContent()->getChannel();

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
                    }
                } catch (Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
        } else {
            if ($addView) {
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
                             * @var Account $channel
                             */
                            $channel = $contentUnit->getContent()->getChannel();

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
                            $blockChain->servedFile($uri, $contentUnitUri, $fileStorageData['address']);
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
                         * @var Account $channel
                         */
                        $channel = $contentUnit->getContent()->getChannel();
                        $storageUrl = $channel->getUrl();
                        $fileUrl = $storageUrl . '/storage?file=' . $file->getUri();

                        $file->setUrl($fileUrl);
                    }
                }
            }
        }

        /**
         * @var Transaction $transaction
         */
        $transaction = $contentUnit->getTransaction();
        $contentUnit->setPublished($transaction->getTimeSigned());

        //  get article next & previous versions
        $previousVersions = $em->getRepository(\App\Entity\ContentUnit::class)->getArticleHistory($contentUnit, true);
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
        }

        $nextVersions = $em->getRepository(\App\Entity\ContentUnit::class)->getArticleHistory($contentUnit, false);
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
        }

        $previousVersions = $this->get('serializer')->normalize($previousVersions, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        $nextVersions = $this->get('serializer')->normalize($nextVersions, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);

        $contentUnit->setPreviousVersions($previousVersions);
        $contentUnit->setNextVersions($nextVersions);

        //  get related articles
        $relatedArticles = $em->getRepository(\App\Entity\ContentUnit::class)->getArticleRelatedArticles($contentUnit, 10);
        if ($relatedArticles) {
            /**
             * @var \App\Entity\ContentUnit $relatedArticle
             */
            foreach ($relatedArticles as $relatedArticle) {
                /**
                 * @var Transaction $transaction
                 */
                $transaction = $relatedArticle->getTransaction();
                $relatedArticle->setPublished($transaction->getTimeSigned());
            }
        }
        //  prepare data to return
        if ($relatedArticles) {
            try {
                $relatedArticles = $contentUnitService->prepare($relatedArticles);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }
        $relatedArticles = $this->get('serializer')->normalize($relatedArticles, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);

        //  check if article boosted
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
        $contentUnit->setBoosted($isBoosted);

        if ($account && $contentUnit->getAuthor() == $account) {
            $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'contentUnitContentId', 'tag', 'file', 'accountBase', 'publication', 'previousVersions', 'nextVersions', 'accountSubscribed']]);
        } else {
            $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'tag', 'file', 'accountBase', 'publication', 'previousVersions', 'nextVersions', 'accountSubscribed']]);
        }

        $contentUnit['related'] = $relatedArticles;

        //  remove files field if served from local
        if ($removeFilesFromResponse) {
            unset($contentUnit['files']);
        }

        return new JsonResponse($contentUnit);
    }

    /**
     * @Route("-seo/{uri}", methods={"GET"}, name="get_content_by_uri_for_seo")
     * @SWG\Get(
     *     summary="Get content by uri for SEO",
     *     consumes={"application/json"},
     *     produces={"application/json"}
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param string $uri
     * @param Custom $customService
     * @return JsonResponse
     */
    public function contentSeo(string $uri, Custom $customService)
    {
        $em = $this->getDoctrine()->getManager();

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($contentUnit->getCover()) {
            /**
             * @var File $file
             */
            $file = $contentUnit->getCover();

            if ($contentUnit->getContent()) {
                /**
                 * @var Account $channel
                 */
                $channel = $contentUnit->getContent()->getChannel();
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

        //  generate short description
        $description = $contentUnit->getTextWithData();
        $description = trim(strip_tags($description));
        if (strlen($description) > 300) {
            $description = substr($description, 0, strpos($description, ' ', 300)) . '...';
        }
        $contentUnit->setDescription($description);

        $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitSeo', 'file', 'accountBase']]);

        return new JsonResponse($contentUnit);
    }
}