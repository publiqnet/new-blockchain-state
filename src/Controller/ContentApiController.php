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
     * @Route("/{uri}", methods={"GET"}, name="get_content_by_uri")
     * @SWG\Get(
     *     summary="Get content by uri",
     *     consumes={"application/json"},
     *     produces={"application/json"}
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
     * @return JsonResponse
     * @throws Exception
     */
    public function content(Request $request, string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger)
    {
        $em = $this->getDoctrine()->getManager();
        $channelAddress = $this->getParameter('channel_address');
        $removeFilesFromResponse = true;

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get user info & determine if view must be added
        $addView = $customService->viewLog($request, $contentUnit);

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

        $previousVersions = $this->get('serializer')->normalize($previousVersions, null, ['groups' => ['contentUnitList', 'file', 'accountBase']]);
        $nextVersions = $this->get('serializer')->normalize($nextVersions, null, ['groups' => ['contentUnitList', 'file', 'accountBase',]]);

        $contentUnit->setPreviousVersions($previousVersions);
        $contentUnit->setNextVersions($nextVersions);

        //  check if article boosted
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
        $contentUnit->setBoosted($isBoosted);

        $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'file', 'accountBase', 'previousVersions', 'nextVersions']]);

        //  remove files field if served from local
        if ($removeFilesFromResponse) {
            unset($contentUnit['files']);
        }

        return new JsonResponse($contentUnit);
    }
}