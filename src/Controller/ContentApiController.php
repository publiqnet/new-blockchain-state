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
use App\Entity\ContentUnitTag;
use App\Entity\File;
use App\Entity\Publication;
use App\Entity\PublicationArticle;
use App\Entity\PublicationMember;
use App\Entity\Tag;
use App\Entity\Transaction;
use App\Service\BlockChain;
use App\Service\ContentUnit as CUService;
use App\Service\Custom;
use Exception;
use Psr\Log\LoggerInterface;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\ContentUnit;
use PubliqAPI\Model\Done;
use PubliqAPI\Model\InvalidSignature;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\StorageFileDetailsResponse;
use PubliqAPI\Model\TransactionDone;
use PubliqAPI\Model\UriError;
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
     * @Route("/unit/upload", methods={"POST"})
     * @SWG\Post(
     *     summary="Upload content unit",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="content", type="string"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param BlockChain $blockChain
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadContentUnit(Request $request, BlockChain $blockChain)
    {
        $em = $this->getDoctrine()->getManager();
        $channelAddress = $this->getParameter('channel_address');

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $content = $content['content'];
        } else {
            $content = $request->request->get('content');
        }

        if (!$content) {
            return new JsonResponse(['message' => 'Empty content'], Response::HTTP_CONFLICT);
        }

        //  generate unique random number for content ID
        $contentId = rand(1, 999999999);
        $channel = $em->getRepository(Account::class)->findOneBy(['publicKey' => $channelAddress]);

        //  check if generated content ID is unique within channel
        $contentUnit = $em->getRepository(\App\Entity\Content::class)->findOneBy(['contentId' => $contentId, 'channel' => $channel]);
        while ($contentUnit) {
            $contentId = rand(1, 999999999);
            $contentUnit = $em->getRepository(\App\Entity\Content::class)->findOneBy(['contentId' => $contentId, 'channel' => $channel]);
        }

        $uploadResult = $blockChain->uploadFile($content, 'text/html');
        if ($uploadResult instanceof StorageFileAddress) {
            return new JsonResponse(['uri' => $uploadResult->getUri(), 'channelAddress' => $channelAddress, 'contentId' => $contentId]);
        } elseif ($uploadResult instanceof UriError) {
            if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                return new JsonResponse(['uri' => $uploadResult->getUri(), 'channelAddress' => $channelAddress, 'contentId' => $contentId]);
            } else {
                return new JsonResponse(['ContentUnit upload error: ' . $uploadResult->getUriProblemType()], Response::HTTP_CONFLICT);
            }
        } else {
            return new JsonResponse(['Error type: ' . get_class($uploadResult)], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/unit/sign", methods={"POST"})
     * @SWG\Post(
     *     summary="Sign content unit",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="uri", type="string"),
     *             @SWG\Property(property="contentId", type="string"),
     *             @SWG\Property(property="signedContentUnit", type="string"),
     *             @SWG\Property(property="creationTime", type="integer"),
     *             @SWG\Property(property="expiryTime", type="integer"),
     *             @SWG\Property(property="fileUris", type="array", items={"type": "string"}),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param Blockchain $blockChain
     * @return JsonResponse
     */
    public function signContentUnit(Request $request, BlockChain $blockChain)
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $uri = $content['uri'];
            $contentId = $content['contentId'];
            $signedContentUnit = $content['signedContentUnit'];
            $creationTime = $content['creationTime'];
            $expiryTime = $content['expiryTime'];
            $fileUris = $content['fileUris'];
        } else {
            $uri = $request->request->get('uri');
            $contentId = $request->request->get('contentId');
            $signedContentUnit = $request->request->get('signedContentUnit');
            $creationTime = $request->request->get('creationTime');
            $expiryTime = $request->request->get('expiryTime');
            $fileUris = $request->request->get('fileUris');
        }

        //  get public key
        $publicKey = $account->getPublicKey();

        try {
            $action = new ContentUnit();
            $action->addAuthorAddresses($account->getPublicKey());
            $action->setUri($uri);
            $action->setContentId($contentId);
            $action->setChannelAddress($this->getParameter('channel_address'));
            if (is_array($fileUris)) {
                foreach ($fileUris as $fileUri) {
                    $action->addFileUris($fileUri);
                }
            }

            //  Verify signature
            $signatureResult = $blockChain->verifySignature($publicKey, $signedContentUnit, $action, $creationTime, $expiryTime);
            if ($signatureResult['signatureResult'] instanceof InvalidSignature) {
                throw new Exception('Invalid signature');
            } elseif ($signatureResult['signatureResult'] instanceof UriError) {
                /**
                 * @var UriError $uriError
                 */
                $uriError = $signatureResult['signatureResult'];
                if ($uriError->getUriProblemType() !== UriProblemType::duplicate) {
                    throw new Exception('Invalid file URI: ' . $uriError->getUri() . '(' . $uriError->getUriProblemType() . ')');
                }
            }

            //  Broadcast
            $broadcastResult = $blockChain->broadcast($signatureResult['transaction'], $publicKey, $signedContentUnit);
            if (!($broadcastResult instanceof Done)) {
                throw new Exception('Broadcasting failed for URI: ' . $uri . '; Error type: ' . get_class($broadcastResult));
            }

            return new JsonResponse('', Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/publish", methods={"POST"})
     * @SWG\Post(
     *     summary="Publish content",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="uri", type="string"),
     *             @SWG\Property(property="contentId", type="string"),
     *             @SWG\Property(property="publicationSlug", type="string"),
     *             @SWG\Property(property="tags", type="string")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param Blockchain $blockChain
     * @return JsonResponse
     */
    public function publishContent(Request $request, BlockChain $blockChain)
    {
        $em = $this->getDoctrine()->getManager();
        $publicationSlug = '';
        $tags = '';

        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse('', Response::HTTP_UNAUTHORIZED);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $uri = $content['uri'];
            $contentId = $content['contentId'];
            if (isset($content['publicationSlug'])) {
                $publicationSlug = $content['publicationSlug'];
            }
            if (isset($content['tags'])) {
                $tags = $content['tags'];
            }
        } else {
            $uri = $request->request->get('uri');
            $contentId = $request->request->get('contentId');
            $publicationSlug = $request->request->get('publicationSlug');
            $tags = $request->request->get('tags');
        }

        try {
            $content = new Content();
            $content->setContentId($contentId);
            $content->setChannelAddress($this->getParameter('channel_address'));
            $content->addContentUnitUris($uri);

            //  relate with tags
            if ($tags) {
                $tags = explode(',', $tags);
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    $tagEntity = $em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                    if (!$tagEntity) {
                        $tagEntity = new Tag();
                        $tagEntity->setName($tag);

                        $em->persist($tagEntity);
                    }

                    $contentUnitTag = new ContentUnitTag();
                    $contentUnitTag->setTag($tagEntity);
                    $contentUnitTag->setContentUnitUri($uri);
                    $em->persist($contentUnitTag);
                    $em->flush();
                }
            }

            //  if publication selected, add temporary record
            if ($publicationSlug) {
                $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $publicationSlug]);
                if ($publication) {
                    //  check if Author is a member of Publication
                    $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
                    if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                        $publicationArticle = new PublicationArticle();
                        $publicationArticle->setPublication($publication);
                        $publicationArticle->setUri($uri);
                        $em->persist($publicationArticle);
                        $em->flush();
                    }
                }
            }

            $broadcastResult = $blockChain->signContent($content, $this->getParameter('channel_private_key'));
            if ($broadcastResult instanceof TransactionDone) {
                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            } else {
                return new JsonResponse(['Error type: ' . get_class($broadcastResult)], Response::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/publication", methods={"POST"})
     * @SWG\Post(
     *     summary="Change content Publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="uri", type="string"),
     *             @SWG\Property(property="publicationSlug", type="string")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @return JsonResponse
     */
    public function changeContentPublication(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse('', Response::HTTP_UNAUTHORIZED);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $uri = $content['uri'];
            $publicationSlug = $content['publicationSlug'];
        } else {
            $uri = $request->request->get('uri');
            $publicationSlug = $request->request->get('publicationSlug');
        }

        try {
            $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
            if (!$contentUnit) {
                return new JsonResponse('', Response::HTTP_NOT_FOUND);
            }

            if ($contentUnit->getAuthor() !== $account) {
                return new JsonResponse('', Response::HTTP_FORBIDDEN);
            }

            if ($publicationSlug) {
                $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $publicationSlug]);
                if (!$publication) {
                    return new JsonResponse('', Response::HTTP_NOT_FOUND);
                }

                //  check if Author is a member of Publication
                $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
                if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                    $contentUnit->setPublication($publication);
                    $em->persist($contentUnit);
                    $em->flush();
                } else {
                    return new JsonResponse('', Response::HTTP_FORBIDDEN);
                }
            } else {
                $contentUnit->setPublication(null);
                $em->persist($contentUnit);
                $em->flush();
            }

            return new JsonResponse('', Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

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
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorArticles($account, $count + 1, $fromContentUnit);
        } else {
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getArticles($count + 1, $fromContentUnit);
        }

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
     * @Route("/{uri}", methods={"GET"})
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
     * @param string $uri
     * @param Blockchain $blockChain
     * @param Custom $customService
     * @param LoggerInterface $logger
     * @return JsonResponse
     * @throws Exception
     */
    public function content(string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger)
    {
        $em = $this->getDoctrine()->getManager();
        $channelAddress = $this->getParameter('channel_address');

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get files & find storage address
        $files = $contentUnit->getFiles();
        $contentUnitUri = $contentUnit->getUri();
        if ($files) {
            $fileStorageUrls = [];

            /**
             * @var File $file
             */
            foreach ($files as $file) {
                $storageUrl = '';
                $storageAddress = '';

                /**
                 * @var Account[] $fileStorages
                 */
                $fileStorages = $customService->getFileStoragesWithPublicAccess($file);
                if (count($fileStorages)) {
                    $randomStorage = rand(0, count($fileStorages) - 1);
                    $storageUrl = $fileStorages[$randomStorage]->getUrl();
                    $storageAddress = $fileStorages[$randomStorage]->getPublicKey();

                    //  get file details
                    if (!$file->getMimeType()) {
                        $fileDetails = $blockChain->getFileDetails($file->getUri(), $storageUrl);
                        if ($fileDetails instanceof StorageFileDetailsResponse) {
                            $file->setMimeType($fileDetails->getMimeType());
                            $file->setSize($fileDetails->getSize());

                            $em->persist($file);
                            $em->flush();
                        }
                    }

                    $file->setUrl($storageUrl . '/storage?file=' . $file->getUri() . '&channel_address=' . $channelAddress);
                } elseif ($contentUnit->getContent()) {
                    /**
                     * @var \App\Entity\Content $content
                     */
                    $content = $contentUnit->getContent();

                    /**
                     * @var Account $channel
                     */
                    $channel = $content->getChannel();

                    $storageUrl = $channel->getUrl();
                    $storageAddress = $channel->getPublicKey();

                    //  get file details
                    if (!$file->getMimeType()) {
                        $fileDetails = $blockChain->getFileDetails($file->getUri(), $storageUrl);
                        if ($fileDetails instanceof StorageFileDetailsResponse) {
                            $file->setMimeType($fileDetails->getMimeType());
                            $file->setSize($fileDetails->getSize());

                            $em->persist($file);
                            $em->flush();
                        }
                    }

                    $file->setUrl($storageUrl . '/storage?file=' . $file->getUri() . '&channel_address=' . $channelAddress);
                }

                $fileStorageUrls[$file->getUri()] = ['url' => $storageUrl, 'address' => $storageAddress];
            }

            //  replace file uri to url
            try {
                foreach ($fileStorageUrls as $uri => $fileStorageData) {
                    $contentUnitText = $contentUnit->getText();
                    $contentUnitText = str_replace('src="' . $uri . '"', 'src="' . $fileStorageData['url'] . '/storage?file=' . $uri . '&channel_address=' . $channelAddress . '"', $contentUnitText);
                    $contentUnit->setText($contentUnitText);

                    //  inform Blockchain about served files
                    $blockChain->servedFile($uri, $contentUnitUri, $fileStorageData['address']);
                }
            } catch (Exception $e) {
                $logger->error($e->getMessage());
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

        if ($account && $contentUnit->getAuthor() == $account) {
            $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'contentUnitContentId', 'tag', 'file', 'accountBase', 'publication']]);
        } else {
            $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'tag', 'file', 'accountBase', 'publication']]);
        }
        $previousVersions = $this->get('serializer')->normalize($previousVersions, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        $nextVersions = $this->get('serializer')->normalize($nextVersions, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);

        $contentUnit['previousVersions'] = $previousVersions;
        $contentUnit['nextVersions'] = $nextVersions;

        return new JsonResponse($contentUnit);
    }

    /**
     * @Route("-boost", methods={"POST"})
     * @SWG\Post(
     *     summary="Boost content",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="signature", type="string"),
     *             @SWG\Property(property="uri", type="string"),
     *             @SWG\Property(property="amount", type="string"),
     *             @SWG\Property(property="hours", type="integer"),
     *             @SWG\Property(property="startTimePoint", type="integer"),
     *             @SWG\Property(property="creationTime", type="integer"),
     *             @SWG\Property(property="expiryTime", type="integer")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param Blockchain $blockChain
     * @return JsonResponse
     */
    public function boostContent(Request $request, BlockChain $blockChain)
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse('', Response::HTTP_PROXY_AUTHENTICATION_REQUIRED);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $signature = $content['signature'];
            $uri = $content['uri'];
            $amount = $content['amount'];
            $hours = $content['hours'];
            $startTimePoint = $content['startTimePoint'];
            $creationTime = $content['creationTime'];
            $expiryTime = $content['expiryTime'];
        } else {
            $signature = $request->request->get('signature');
            $uri = $request->request->get('uri');
            $amount = $request->request->get('amount');
            $hours = $request->request->get('hours');
            $startTimePoint = $request->request->get('startTimePoint');
            $creationTime = $request->request->get('creationTime');
            $expiryTime = $request->request->get('expiryTime');
        }

        try {
            $broadcastResult = $blockChain->boostContent($signature, $uri, $account->getPublicKey(), $amount, $hours, $startTimePoint, $creationTime, $expiryTime);
            if ($broadcastResult instanceof Done) {
                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            } else {
                return new JsonResponse(['Error type: ' . get_class($broadcastResult)], Response::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("-boost", methods={"GET"})
     * @SWG\Get(
     *     summary="Get author boosted articles",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @return JsonResponse
     * @throws Exception
     */
    public function getBoosts()
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse('', Response::HTTP_PROXY_AUTHENTICATION_REQUIRED);
        }

        $boostedContentUnits = $em->getRepository(BoostedContentUnit::class)->getAuthorBoostedArticles($account);
        if ($boostedContentUnits) {
            /**
             * @var BoostedContentUnit $boostedContentUnit
             */
            foreach ($boostedContentUnits as $boostedContentUnit) {
                /**
                 * @var \App\Entity\ContentUnit $contentUnit
                 */
                $contentUnit = $boostedContentUnit->getContentUnit();

                /**
                 * @var Transaction $transaction
                 */
                $transaction = $contentUnit->getTransaction();

                $contentUnit->setPublished($transaction->getTimeSigned());
            }
        }
        $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['boostedContentUnit', 'contentUnitList', 'tag', 'accountBase', 'publication']]);

        return new JsonResponse($boostedContentUnits);
    }
}