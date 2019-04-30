<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/18/19
 * Time: 12:13 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Service\BlockChain;
use Exception;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\ContentUnit;
use PubliqAPI\Model\Done;
use PubliqAPI\Model\InvalidSignature;
use PubliqAPI\Model\StorageFileAddress;
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
     * @SWG\Tag(name="ContentUnit")
     * @param Request $request
     * @param BlockChain $blockChain
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadContentUnit(Request $request, BlockChain $blockChain)
    {
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

        $uploadResult = $blockChain->uploadFile($content, 'text/html');
        if ($uploadResult instanceof StorageFileAddress) {
            return new JsonResponse(['uri' => $uploadResult->getUri(), 'channelAddress' => $this->getParameter('channel_address')]);
        } elseif ($uploadResult instanceof UriError) {
            if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                return new JsonResponse(['uri' => $uploadResult->getUri(), 'channelAddress' => $this->getParameter('channel_address')]);
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
     * @SWG\Tag(name="ContentUnit")
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
        $publicKey = $account->getAddress();

        try {
            $action = new ContentUnit();
            $action->addAuthorAddresses($account->getAddress());
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
     *             @SWG\Property(property="contentId", type="string")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="ContentUnit")
     * @param Request $request
     * @param Blockchain $blockChain
     * @return JsonResponse
     */
    public function publishContent(Request $request, BlockChain $blockChain)
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
        } else {
            $uri = $request->request->get('uri');
            $contentId = $request->request->get('contentId');
        }

        try {
            $content = new Content();
            $content->setContentId($contentId);
            $content->setChannelAddress($this->getParameter('channel_address'));
            $content->addContentUnitUris($uri);

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
}