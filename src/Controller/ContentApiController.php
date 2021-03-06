<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/18/19
 * Time: 12:13 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\AccountContentUnit;
use App\Entity\BoostedContentUnit;
use App\Entity\BoostedContentUnitSpending;
use App\Entity\CancelBoostedContentUnit;
use App\Entity\Content;
use App\Entity\ContentUnitTag;
use App\Entity\ContentUnitViews;
use App\Entity\Draft;
use App\Entity\File;
use App\Entity\Publication;
use App\Entity\PublicationArticle;
use App\Entity\PublicationMember;
use App\Entity\Subscription;
use App\Entity\Tag;
use App\Entity\Transaction;
use App\Event\UserPreferenceEvent;
use App\Service\BlockChain;
use App\Service\ContentUnit as CUService;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\ContentUnit;
use PubliqAPI\Model\Done;
use PubliqAPI\Model\InvalidSignature;
use PubliqAPI\Model\NotEnoughBalance;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\UriError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ContentApiController
 * @package App\Controller
 * @Route("/api/content")
 */
class ContentApiController extends AbstractController
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
        /**
         * @var EntityManager $em
         */
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
        $contentUnit = $em->getRepository(Content::class)->findOneBy(['contentId' => $contentId, 'channel' => $channel]);
        while ($contentUnit) {
            $contentId = rand(1, 999999999);
            $contentUnit = $em->getRepository(Content::class)->findOneBy(['contentId' => $contentId, 'channel' => $channel]);
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
     *             @SWG\Property(property="publicationSlug", type="string"),
     *             @SWG\Property(property="tags", type="array", items={"type": "string"}),
     *             @SWG\Property(property="feeWhole", type="integer"),
     *             @SWG\Property(property="feeFraction", type="integer")
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
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $publicationSlug = '';

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
            if (isset($content['publicationSlug'])) {
                $publicationSlug = $content['publicationSlug'];
            }
            $tags = $content['tags'];
            $feeWhole = intval($content['feeWhole']);
            $feeFraction = intval($content['feeFraction']);
            $currentTransactionHash = $content['currentTransactionHash'];
            $draftId = $content['draftId'];
        } else {
            $uri = $request->request->get('uri');
            $contentId = $request->request->get('contentId');
            $signedContentUnit = $request->request->get('signedContentUnit');
            $creationTime = $request->request->get('creationTime');
            $expiryTime = $request->request->get('expiryTime');
            $fileUris = $request->request->get('fileUris');
            $publicationSlug = $request->request->get('publicationSlug');
            $tags = $request->request->get('tags');
            $feeWhole = intval($request->request->get('feeWhole'));
            $feeFraction = intval($request->request->get('feeFraction'));
            $currentTransactionHash = $request->request->get('currentTransactionHash');
            $draftId = $request->request->get('draftId');
        }

        if (!$currentTransactionHash) {
            return new JsonResponse(['type' => 'story_no_transaction_hash'], Response::HTTP_CONFLICT);
        }

        //  get public key
        $publicKey = $account->getPublicKey();

        try {
            $em->beginTransaction();

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
            $signatureResult = $blockChain->verifySignature($publicKey, $signedContentUnit, $action, $creationTime, $expiryTime, $feeWhole, $feeFraction);
            if ($signatureResult['signatureResult'] instanceof InvalidSignature) {
                return new JsonResponse(['type' => 'story_invalid_signature'], Response::HTTP_CONFLICT);
            } elseif ($signatureResult['signatureResult'] instanceof UriError) {
                /**
                 * @var UriError $uriError
                 */
                $uriError = $signatureResult['signatureResult'];
                if ($uriError->getUriProblemType() !== UriProblemType::duplicate) {
                    throw new Exception('Invalid file URI: ' . $uriError->getUri() . '(' . $uriError->getUriProblemType() . ')');
                }
            }

            //  if content unit exists update relation to tags & publication, else add new record
            $contentUnitEntity = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
            if (!$contentUnitEntity) {
                //  Broadcast
                $broadcastResult = $blockChain->broadcast($signatureResult['transaction'], $publicKey, $signedContentUnit);
                if ($broadcastResult instanceof UriError && $broadcastResult->getUriProblemType() == UriProblemType::duplicate) {
                    return new JsonResponse(['type' => 'duplicate_uri'], Response::HTTP_CONFLICT);
                } elseif ($broadcastResult instanceof UriError) {
                    return new JsonResponse(['type' => 'system_error', 'msg' => 'Broadcasting failed for URI: ' . $uri . '; Error type: ' . $broadcastResult->getUriProblemType()], Response::HTTP_CONFLICT);
                } elseif ($broadcastResult instanceof NotEnoughBalance) {
                    return new JsonResponse(['type' => 'story_not_enough_balance'], Response::HTTP_CONFLICT);
                } elseif (!($broadcastResult instanceof Done)) {
                    return new JsonResponse(['type' => 'system_error', 'msg' => 'Broadcasting failed for URI: ' . $uri . '; Error type: ' . get_class($broadcastResult)], Response::HTTP_CONFLICT);
                }

                //  get channel account
                $channelPublicKey = $this->getParameter('channel_address');
                $channelAccount = $em->getRepository(Account::class)->findOneBy(['publicKey' => $channelPublicKey]);

                //  get content unit data
                $coverUri = null;
                $storageData = $blockChain->getContentUnitData($uri);
                if (strpos($storageData, '</h1>')) {
                    if (strpos($storageData, '<h1>') > 0) {
                        $coverPart = substr($storageData, 0, strpos($storageData, '<h1>'));

                        $coverPart = substr($coverPart, strpos($coverPart,'src="') + 5);
                        $coverUri = substr($coverPart, 0, strpos($coverPart, '"'));
                    }
                    $contentUnitTitle = strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5));
                    $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                } else {
                    $contentUnitTitle = 'Old content without title';
                    $contentUnitText = $storageData;
                }

                //  create content unit
                $contentUnitEntity = new \App\Entity\ContentUnit();
                $contentUnitEntity->setAuthor($account);
                $contentUnitEntity->setUri($uri);
                $contentUnitEntity->setContentId($contentId);
                $contentUnitEntity->setChannel($channelAccount);
                $contentUnitEntity->setTitle($contentUnitTitle);
                $contentUnitEntity->setText($contentUnitText);
                $contentUnitEntity->setTextWithData($contentUnitText);
                if ($coverUri) {
                    $coverFileEntity = $em->getRepository(File::class)->findOneBy(['uri' => $coverUri]);
                    if (!$coverFileEntity) {
                        $coverFileEntity = new File();
                        $coverFileEntity->setUri($coverUri);
                        $em->persist($coverFileEntity);
                    }
                    $contentUnitEntity->setCover($coverFileEntity);
                }

                $accountContentUnit = new AccountContentUnit();
                $accountContentUnit->setAccount($account);
                $accountContentUnit->setContentUnit($contentUnitEntity);
                $accountContentUnit->setSigned(true);
                $accountContentUnit->setSignature($signedContentUnit);
                $em->persist($accountContentUnit);

                $contentUnitEntity->addAuthor($accountContentUnit);

                //  add temporary transaction with size = 0
                $transactionEntity = new Transaction();
                $transactionEntity->setTransactionHash($currentTransactionHash);
                $transactionEntity->setContentUnit($contentUnitEntity);
                $transactionEntity->setTimeSigned($creationTime);
                $transactionEntity->setFeeWhole($feeWhole);
                $transactionEntity->setFeeFraction($feeFraction);
                $transactionEntity->setTransactionSize(0);
                $em->persist($transactionEntity);

                //  relate draft with article
                $draft = $em->getRepository(Draft::class)->find($draftId);
                if ($draft) {
                    $draft->setUri($contentUnitEntity->getUri());
                    $draft->setPublished(true);
                    $em->persist($draft);
                }

                $publishContentUnitResult = $blockChain->publishContentUnit($contentUnitEntity, $draft);
                if (!$publishContentUnitResult['status']) {
                    return new JsonResponse([$publishContentUnitResult['message']], Response::HTTP_CONFLICT);
                }
            } elseif (!$contentUnitEntity->getTransaction()) {
                //  add temporary transaction with size = 0
                $transactionEntity = new Transaction();
                $transactionEntity->setTransactionHash($currentTransactionHash);
                $transactionEntity->setContentUnit($contentUnitEntity);
                $transactionEntity->setTimeSigned($creationTime);
                $transactionEntity->setFeeWhole($feeWhole);
                $transactionEntity->setFeeFraction($feeFraction);
                $transactionEntity->setTransactionSize(0);
                $em->persist($transactionEntity);

                //  relate draft with article
                $draft = $em->getRepository(Draft::class)->find($draftId);
                if ($draft) {
                    $draft->setUri($contentUnitEntity->getUri());
                    $draft->setPublished(true);
                    $em->persist($draft);
                }

                $publishContentUnitResult = $blockChain->publishContentUnit($contentUnitEntity, $draft);
                if (!$publishContentUnitResult['status']) {
                    return new JsonResponse([$publishContentUnitResult['message']], Response::HTTP_CONFLICT);
                }
            }

            //  relate to publication
            if ($publicationSlug) {
                $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $publicationSlug]);
                if ($publication) {
                    //  check if Author is a member of Publication
                    $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $account]);
                    if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                        $contentUnitEntity->setPublication($publication);
                        $em->persist($contentUnitEntity);

                        $publicationArticle = new PublicationArticle();
                        $publicationArticle->setPublication($publication);
                        $publicationArticle->setUri($uri);
                        $em->persist($publicationArticle);
                    }
                }
            } else {
                $contentUnitEntity->setPublication(null);
            }

            //  remove existing tags
            $contentUnitTags = $em->getRepository(ContentUnitTag::class)->findBy(['contentUnit' => $contentUnitEntity]);
            if ($contentUnitTags) {
                foreach ($contentUnitTags as $contentUnitTag) {
                    $em->remove($contentUnitTag);
                }
            }

            //  add tags
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $tag = substr(trim($tag), 0, 24);
                    $tagEntity = $em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                    if (!$tagEntity) {
                        $tagEntity = new Tag();
                        $tagEntity->setName($tag);
                        $em->persist($tagEntity);
                    }

                    $contentUnitTag = new ContentUnitTag();
                    $contentUnitTag->setTag($tagEntity);
                    $contentUnitTag->setContentUnitUri($uri);
                    $contentUnitTag->setContentUnit($contentUnitEntity);

                    $em->persist($contentUnitTag);
                }
            }

            $em->persist($contentUnitEntity);
            $em->flush();
            $em->commit();

            return new JsonResponse('', Response::HTTP_NO_CONTENT);
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
        /**
         * @var EntityManager $em
         */
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

            $isOwner = false;
            if ($account) {
                /**
                 * @var AccountContentUnit[] $contentUnitAuthors
                 */
                $contentUnitAuthors = $contentUnit->getAuthors();
                foreach ($contentUnitAuthors as $contentUnitAuthor) {
                    if ($contentUnitAuthor->getAccount() === $account) {
                        $isOwner = true;
                        break;
                    }
                }
            }

            if (!$isOwner) {
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
     * @Route("/detect-language", methods={"POST"})
     * @SWG\Post(
     *     summary="Detect content language",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="text", type="string")
     *         )
     *     )
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Content")
     * @param Request $request
     * @param BlockChain $blockChainService
     * @return JsonResponse
     * @throws Exception
     */
    public function detectContentLanguage(Request $request, BlockChain $blockChainService)
    {
        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $text = $content['text'];
        } else {
            $text = $request->request->get('text');
        }

        list($detectResult, $keywords) = $blockChainService->detectContentLanguageKeywords($text);
        if (is_array($detectResult) && count($detectResult)) {
            foreach ($detectResult as $language => $possibility) {
                return new JsonResponse(['code' => $language, 'internationalName' => $possibility[1], 'nativeName' => $possibility[2], 'keywords' => $keywords]);
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("s/{count}/{boostedCount}/{fromUri}", methods={"GET"}, name="get_contents_feed")
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
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws Exception
     */
    public function contents(int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }
        $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getArticles($count + 1, $fromContentUnit);

        //  prepare data to return
        if ($contentUnits) {
            $contentUnits = $contentUnitService->prepare($contentUnits, null, $account);
        }

        //  disable channel exclude filter
        if ($this->getParameter('boosted_articles_from_excluded_channels') == 'show') {
            $em->getFilters()->disable('channel_exclude_filter');
        }

        $boostedContentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getBoostedArticles($boostedCount, $contentUnits);
        if ($boostedContentUnits) {
            $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true, $account);
        }

        $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'previousVersions']]);
        $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'previousVersions']]);

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

        $contentUnits = $contentUnitService->prepareTags($contentUnits);

        return new JsonResponse(['data' => $contentUnits, 'more' => $more]);
    }

    /**
     * @Route("s/{publicKey}/{count}/{boostedCount}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get custom user contents",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
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
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws Exception
     */
    public function authorContents(string $publicKey, int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }

        if ($account === $author) {
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorArticles($author, $count + 1, $fromContentUnit, true);
        } else {
            $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorArticles($author, $count + 1, $fromContentUnit);
        }

        //  disable channel exclude filter
        if ($this->getParameter('boosted_articles_from_excluded_channels') == 'show') {
            $em->getFilters()->disable('channel_exclude_filter');
        }

        $boostedContentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getBoostedArticles($boostedCount, $contentUnits);

        //  prepare data to return
        if ($contentUnits) {
            $contentUnits = $contentUnitService->prepare($contentUnits, null, $account);
        }

        if ($boostedContentUnits) {
            $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true, $account);
        }

        if ($account === $author) {
            $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'previousVersions', 'boost', 'boostedContentUnitMain', 'transactionLight']]);
            $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        } else {
            $contentUnits = $this->get('serializer')->normalize($contentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
            $boostedContentUnits = $this->get('serializer')->normalize($boostedContentUnits, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'previousVersions']]);
        }

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

        $contentUnits = $contentUnitService->prepareTags($contentUnits);

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
     * @param EventDispatcherInterface $eventDispatcher
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws Exception
     */
    public function content(Request $request, string $uri, BlockChain $blockChain, Custom $customService, LoggerInterface $logger, CUService $contentUnitService, EventDispatcherInterface $eventDispatcher)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        //  disable channel exclude filter
        if ($this->getParameter('boosted_articles_from_excluded_channels') == 'show') {
            $em->getFilters()->disable('channel_exclude_filter');
        }

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  if article is not boosted & from blacklisted channel, return not found
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);

        /**
         * @var Account $contentUnitChannel
         */
        $contentUnitChannel = $contentUnit->getChannel();
        if (($contentUnitChannel->isExcluded() && !$isBoosted) || $contentUnit->isExcluded()) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get user info
        $userIdentifier = $customService->viewLog($request, $contentUnit, $account);

        // update user preference if viewer is not article author
        $isOwner = false;
        $disableViews = false;

        /**
         * @var AccountContentUnit[] $contentUnitAuthors
         */
        $contentUnitAuthors = $contentUnit->getAuthors();
        foreach ($contentUnitAuthors as $contentUnitAuthor) {
            if ($contentUnitAuthor->getAccount() === $account) {
                $isOwner = true;
            }

            if ($contentUnitAuthor->getAccount()->isDisableViews()) {
                $disableViews = true;
            }
        }

        if (!$isOwner && $account) {
            $eventDispatcher->dispatch(
                new UserPreferenceEvent($account, $contentUnit),
                UserPreferenceEvent::NAME
            );
        }

        //  if viewer is article author return full data without adding view
        if ($isOwner || $disableViews) {
            //  get files & find storage address
            $files = $contentUnit->getFiles();
            if ($files) {
                $fileStorageUrls = [];

                /**
                 * @var File $file
                 */
                foreach ($files as $file) {
                    /**
                     * @var Account $channel
                     */
                    $channel = $contentUnit->getChannel();

                    /**
                     * @var Account $firstChannel
                     */
                    $firstChannel = $em->getRepository(Account::class)->getFileFirstChannel($file);
                    if ($firstChannel && $firstChannel->getUrl()) {
                        $channel = $firstChannel;
                    }

                    $fileUrl = $channel->getUrl() . '/storage?file=' . $file->getUri();

                    $file->setUrl($fileUrl);

                    $fileStorageUrls[$file->getUri()] = ['url' => $fileUrl, 'address' => $channel->getPublicKey()];
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

                        $fileUrl = $channel->getUrl() . '/storage?file=' . $file->getUri();

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
        }

        /**
         * @var Transaction $transaction
         */
        $transaction = $contentUnit->getTransaction();
        $contentUnit->setPublished($transaction->getTimeSigned());
        if ($transaction->getBlock()) {
            $contentUnit->setStatus('confirmed');
        } else {
            $contentUnit->setStatus('pending');
        }

        //  get article next & previous versions
        $em->getFilters()->enable('channel_exclude_filter');

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

        $previousVersions = $contentUnitService->prepareTags($previousVersions);
        $nextVersions = $contentUnitService->prepareTags($nextVersions);

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
                $relatedArticles = $contentUnitService->prepare($relatedArticles, null, $account);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }
        $relatedArticles = $this->get('serializer')->normalize($relatedArticles, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        $relatedArticles = $contentUnitService->prepareTags($relatedArticles);

        //  check if article boosted
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
        $contentUnit->setBoosted($isBoosted);

        //  check if user subscribed to author
        if ($account) {
            /**
             * @var AccountContentUnit $accountContentUnit
             */
            $accountContentUnit = $contentUnit->getAuthors()[0];

            /**
             * @var Account $author
             */
            $author = $accountContentUnit->getAccount();
            $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);
            $author->setSubscribed($subscribed ? true : false);
        }

        $contentUnit = $this->get('serializer')->normalize($contentUnit, null, ['groups' => ['contentUnitFull', 'contentUnitContentId', 'tag', 'file', 'accountBase', 'publication', 'previousVersions', 'nextVersions', 'accountSubscribed']]);

        $contentUnit = $contentUnitService->prepareTags($contentUnit, false);
        $contentUnit['related'] = $relatedArticles;

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
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function contentSeo(string $uri)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        //  disable channel exclude filter
        if ($this->getParameter('boosted_articles_from_excluded_channels') == 'show') {
            $em->getFilters()->disable('channel_exclude_filter');
        }

        $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  if article is not boosted & from blacklisted channel, return not found
        $isBoosted = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);

        /**
         * @var Account $contentUnitChannel
         */
        $contentUnitChannel = $contentUnit->getChannel();
        if (($contentUnitChannel->isExcluded() && !$isBoosted) || $contentUnit->isExcluded()) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($contentUnit->getCover()) {
            /**
             * @var File $file
             */
            $file = $contentUnit->getCover();

            /**
             * @var Account $channel
             */
            $channel = $contentUnit->getChannel();

            $file->setUrl($channel->getUrl() . '/storage?file=' . $file->getUri());
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
     *             @SWG\Property(property="whole", type="string"),
     *             @SWG\Property(property="fraction", type="string"),
     *             @SWG\Property(property="hours", type="integer"),
     *             @SWG\Property(property="startTimePoint", type="integer"),
     *             @SWG\Property(property="creationTime", type="integer"),
     *             @SWG\Property(property="expiryTime", type="integer"),
     *             @SWG\Property(property="feeWhole", type="integer"),
     *             @SWG\Property(property="feeFraction", type="integer")
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
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

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
            $whole = $content['whole'];
            $fraction = $content['fraction'];
            $hours = $content['hours'];
            $startTimePoint = $content['startTimePoint'];
            $creationTime = $content['creationTime'];
            $expiryTime = $content['expiryTime'];
            $feeWhole = $content['feeWhole'];
            $feeFraction = $content['feeFraction'];
            $currentTransactionHash = $content['currentTransactionHash'];
        } else {
            $signature = $request->request->get('signature');
            $uri = $request->request->get('uri');
            $whole = $request->request->get('whole');
            $fraction = $request->request->get('fraction');
            $hours = $request->request->get('hours');
            $startTimePoint = $request->request->get('startTimePoint');
            $creationTime = $request->request->get('creationTime');
            $expiryTime = $request->request->get('expiryTime');
            $feeWhole = $request->request->get('feeWhole');
            $feeFraction = $request->request->get('feeFraction');
            $currentTransactionHash = $request->request->get('currentTransactionHash');
        }

        try {
            $broadcastResult = $blockChain->boostContent($signature, $uri, $account->getPublicKey(), $whole, $fraction, $hours, $startTimePoint, $creationTime, $expiryTime, $feeWhole, $feeFraction);
            if ($broadcastResult instanceof Done) {
                if ($currentTransactionHash) {
                    $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);

                    $em->beginTransaction();

                    //  add boosted content unit
                    $boostedContentUnit = new BoostedContentUnit();
                    $boostedContentUnit->setSponsor($account);
                    $boostedContentUnit->setContentUnit($contentUnit);
                    $boostedContentUnit->setStartTimePoint($startTimePoint);
                    $boostedContentUnit->setHours($hours);
                    $boostedContentUnit->setWhole($whole);
                    $boostedContentUnit->setFraction($fraction);
                    $boostedContentUnit->setFraction(0);
                    $boostedContentUnit->setEndTimePoint($startTimePoint + $hours * 3600);
                    $em->persist($boostedContentUnit);
                    $em->flush();

                    //  add transaction
                    $timezone = new \DateTimeZone('UTC');
                    $datetime = new \DateTime();
                    $datetime->setTimezone($timezone);

                    $transaction = new Transaction();
                    $transaction->setTransactionHash($currentTransactionHash);
                    $transaction->setBoostedContentUnit($boostedContentUnit);
                    $transaction->setTimeSigned($datetime->getTimestamp());
                    $transaction->setFeeWhole($feeWhole);
                    $transaction->setFeeFraction($feeFraction);
                    $transaction->setTransactionSize(0);
                    $em->persist($transaction);
                    $em->flush();

                    $em->commit();
                }

                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            } elseif ($broadcastResult instanceof NotEnoughBalance) {
                return new JsonResponse(['type' => 'boost_not_enough_balance'], Response::HTTP_CONFLICT);
            } elseif ($broadcastResult instanceof InvalidSignature) {
                return new JsonResponse(['type' => 'boost_invalid_signature'], Response::HTTP_CONFLICT);
            } else {
                return new JsonResponse(['Error type: ' . get_class($broadcastResult)], Response::HTTP_CONFLICT);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("-highlight", methods={"POST"})
     * @SWG\Post(
     *     summary="Highlight content",
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
     *             @SWG\Property(property="background", type="string"),
     *             @SWG\Property(property="font", type="string"),
     *             @SWG\Property(property="tagClass", type="string")
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
    public function boostHighlight(Request $request)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

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

            $uri = $content['uri'];
            $background = $content['background'];
            $font = $content['font'];
            $tagClass = $content['tagClass'];
        } else {
            $uri = $request->request->get('uri');
            $background = $request->request->get('background');
            $font = $request->request->get('font');
            $tagClass = $request->request->get('tagClass');
        }

        try {
            $contentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
            if (!$contentUnit) {
                return new JsonResponse(null, Response::HTTP_NOT_FOUND);
            }

            //  font is required
            if (!$font) {
                return new JsonResponse(['type' => 'highlight_font_required'], Response::HTTP_CONFLICT);
            }

            //  background is required if article has no cover
            if (!$background && !$contentUnit->getCover() && !$contentUnit->getCoverExternalUrl()) {
                return new JsonResponse(['type' => 'highlight_background_required'], Response::HTTP_CONFLICT);
            }

            $contentUnit->setHighlight(true);
            $contentUnit->setHighlightBackground($background);
            $contentUnit->setHighlightFont($font);
            $contentUnit->setHighlightTagClass($tagClass);
            $em->persist($contentUnit);
            $em->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("-boost-cancel", methods={"POST"})
     * @SWG\Post(
     *     summary="Cancel boosted content",
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
     *             @SWG\Property(property="transactionHash", type="string"),
     *             @SWG\Property(property="creationTime", type="integer"),
     *             @SWG\Property(property="expiryTime", type="integer"),
     *             @SWG\Property(property="feeWhole", type="integer"),
     *             @SWG\Property(property="feeFraction", type="integer")
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
    public function cancelBoostContent(Request $request, BlockChain $blockChain)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

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
            $transactionHash = $content['transactionHash'];
            $creationTime = $content['creationTime'];
            $expiryTime = $content['expiryTime'];
            $feeWhole = $content['feeWhole'];
            $feeFraction = $content['feeFraction'];
            $currentTransactionHash = $content['currentTransactionHash'];
        } else {
            $signature = $request->request->get('signature');
            $uri = $request->request->get('uri');
            $transactionHash = $request->request->get('transactionHash');
            $creationTime = $request->request->get('creationTime');
            $expiryTime = $request->request->get('expiryTime');
            $feeWhole = $request->request->get('feeWhole');
            $feeFraction = $request->request->get('feeFraction');
            $currentTransactionHash = $request->request->get('currentTransactionHash');
        }

        try {
            $broadcastResult = $blockChain->cancelBoostContent($signature, $uri, $account->getPublicKey(), $transactionHash, $creationTime, $expiryTime, $feeWhole, $feeFraction);
            if ($broadcastResult instanceof Done) {
                $timezone = new \DateTimeZone('UTC');
                $datetime = new \DateTime();
                $datetime->setTimezone($timezone);

                $boostTransaction = $em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);

                /**
                 * @var BoostedContentUnit $boostedContentUnit
                 */
                $boostedContentUnit = $boostTransaction->getBoostedContentUnit();

                $em->beginTransaction();

                //  add cancelled boosted content unit
                $cancelBoostedContentUnitEntity = new CancelBoostedContentUnit();
                $cancelBoostedContentUnitEntity->setBoostedContentUnit($boostedContentUnit);
                $em->persist($cancelBoostedContentUnitEntity);

                $boostedContentUnit->setCancelled(true);
                $boostedContentUnit->setCancelBoostedContentUnit($cancelBoostedContentUnitEntity);
                $boostedContentUnit->setEndTimePoint($datetime->getTimestamp());
                $em->persist($boostedContentUnit);
                $em->flush();

                //  add transaction
                $transaction = new Transaction();
                $transaction->setTransactionHash($currentTransactionHash);
                $transaction->setCancelBoostedContentUnit($cancelBoostedContentUnitEntity);
                $transaction->setTimeSigned($datetime->getTimestamp());
                $transaction->setFeeWhole($feeWhole);
                $transaction->setFeeFraction($feeFraction);
                $transaction->setTransactionSize(0);
                $em->persist($transaction);
                $em->flush();

                $em->commit();

                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            } elseif ($broadcastResult instanceof InvalidSignature) {
                return new JsonResponse(['type' => 'boost_invalid_signature'], Response::HTTP_CONFLICT);
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
     * @param CUService $contentUnitService
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getBoosts(CUService $contentUnitService)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse('', Response::HTTP_PROXY_AUTHENTICATION_REQUIRED);
        }

        $active = [];
        $passive = [];

        /**
         * @var \App\Entity\ContentUnit[] $contentUnits
         */
        $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getAuthorRelatedBoosts($account);
        if ($contentUnits) {
            foreach ($contentUnits as $contentUnit) {
                $views = 0;
                $channels = 0;
                $viewsSummary = $em->getRepository(ContentUnitViews::class)->getBoostedArticleSummary($contentUnit);
                foreach ($viewsSummary as $viewsSummarySingle) {
                    $views += $viewsSummarySingle['views'];
                    $channels++;
                }
                $viewsSummary[0] = ['views' => $views, 'channels' => $channels];

                $summary = $em->getRepository(BoostedContentUnit::class)->getBoostedArticleSummary($contentUnit);
                if (!isset($summary[0])) {
                    $summary[0] = [];
                }

                $spendingSummary = $em->getRepository(BoostedContentUnitSpending::class)->getBoostedArticleSummary($contentUnit);
                if (!isset($spendingSummary[0])) {
                    $spendingSummary[0] = ['spentWhole' => 0, 'spentFraction' => 0];
                } else {
                    $spentWhole = intval($spendingSummary[0]['spentWhole']);
                    $spentFraction = intval($spendingSummary[0]['spentFraction']);

                    if ($spentFraction > 99999999) {
                        while ($spentFraction > 99999999) {
                            $spentWhole++;
                            $spentFraction -= 100000000;
                        }
                    }

                    $spendingSummary[0]['spentWhole'] = $spentWhole;
                    $spendingSummary[0]['spentFraction'] = $spentFraction;
                }

                $summary = array_merge($summary[0], $viewsSummary[0], $spendingSummary[0]);
                $contentUnit->setBoostSummary($summary);

                /**
                 * @var BoostedContentUnit[] $boosts
                 */
                $boosts = $em->getRepository(BoostedContentUnit::class)->getArticleBoostsForUser($contentUnit, $account);
                foreach ($boosts as $boost) {
                    $spendingSummary = $em->getRepository(BoostedContentUnitSpending::class)->getBoostSummary($boost);
                    if (!isset($spendingSummary[0])) {
                        $spendingSummary[0] = ['spentWhole' => 0, 'spentFraction' => 0];
                    } else {
                        $spentWhole = intval($spendingSummary[0]['spentWhole']);
                        $spentFraction = intval($spendingSummary[0]['spentFraction']);

                        if ($spentFraction > 99999999) {
                            while ($spentFraction > 99999999) {
                                $spentWhole++;
                                $spentFraction -= 100000000;
                            }
                        }

                        $spendingSummary[0]['spentWhole'] = $spentWhole;
                        $spendingSummary[0]['spentFraction'] = $spentFraction;
                    }

                    $boost->setSummary($spendingSummary[0]);
                }
                $contentUnit->setBoosts($boosts);

                $isBoostActive = $em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
                if ($isBoostActive) {
                    $active[] = $contentUnit;
                } else {
                    $passive[] = $contentUnit;
                }
            }
        }

        //  prepare data to return
        if ($active) {
            try {
                $active = $contentUnitService->prepare($active);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }
        if ($passive) {
            try {
                $passive = $contentUnitService->prepare($passive);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }

        //  get boost summary
        $views = 0;
        $channels = 0;
        $boostSummaryViews = $em->getRepository(ContentUnitViews::class)->getAuthorBoostedArticlesSummary($account);
        foreach ($boostSummaryViews as $boostSummaryViewsSingle) {
            $views += $boostSummaryViewsSingle['views'];
            $channels++;
        }
        $boostSummaryViews[0] = ['views' => $views, 'channels' => $channels];

        $boostSummary = $em->getRepository(BoostedContentUnit::class)->getAuthorBoostedArticlesSummary($account);
        if (!isset($boostSummary[0])) {
            $boostSummary[0] = [];
        }

        $boostSummarySpending = $em->getRepository(BoostedContentUnitSpending::class)->getAuthorBoostedArticlesSummary($account);
        if (!isset($boostSummarySpending[0])) {
            $boostSummarySpending[0] = ['spentWhole' => 0, 'spentFraction' => 0];
        } else {
            $spentWhole = intval($boostSummarySpending[0]['spentWhole']);
            $spentFraction = intval($boostSummarySpending[0]['spentFraction']);

            if ($spentFraction > 99999999) {
                while ($spentFraction > 99999999) {
                    $spentWhole++;
                    $spentFraction -= 100000000;
                }
            }

            $boostSummarySpending[0]['spentWhole'] = $spentWhole;
            $boostSummarySpending[0]['spentFraction'] = $spentFraction;
        }

        $active = $this->get('serializer')->normalize($active, null, ['groups' => ['boostedContentUnitMain', 'contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'transactionLight', 'boost']]);
        $passive = $this->get('serializer')->normalize($passive, null, ['groups' => ['boostedContentUnitMain', 'contentUnitList', 'tag', 'file', 'accountBase', 'publication', 'transactionLight', 'boost']]);

        $boostSummary = array_merge($boostSummary[0], $boostSummaryViews[0], $boostSummarySpending[0]);

        return new JsonResponse(['active' => $active, 'passive' => $passive, 'summary' => $boostSummary]);
    }
}