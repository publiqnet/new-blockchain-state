<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/11/19
 * Time: 1:10 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Service\BlockChain;
use Exception;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\Done;
use PubliqAPI\Model\File;
use PubliqAPI\Model\NotEnoughBalance;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\UriError;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FileApiController
 * @package App\Controller
 * @Route("/api/file")
 */
class FileApiController extends Controller
{
    /**
     * @Route("/upload", methods={"POST"})
     * @SWG\Post(
     *     summary="Upload file",
     *     consumes={"multipart/form-data"},
     *     @SWG\Parameter(name="file", in="formData", type="file", description="File"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string"),
     * )
     * @SWG\Response(response=200, description="successfully uploaded")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="something went wrong")
     * @SWG\Tag(name="File")
     * @param Request $request
     * @param BlockChain $blockChain
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadFile(Request $request, BlockChain $blockChain)
    {
        /**
         * @var UploadedFile $file
         */
        $file = $request->files->get('file');
        $channelStorageEndpoint = $this->getParameter('channel_storage_endpoint');

        if ($file && $file->getClientMimeType()) {
            $fileData = file_get_contents($file->getRealPath());

            $uploadResult = $blockChain->uploadFile($fileData, $file->getMimeType());
            if ($uploadResult instanceof StorageFileAddress) {
                return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri()]);
            } elseif ($uploadResult instanceof UriError) {
                if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                    return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri()]);
                } else {
                    return new JsonResponse(['File upload error: ' . $uploadResult->getUriProblemType()], Response::HTTP_CONFLICT);
                }
            } else {
                return new JsonResponse(['Error type: ' . get_class($uploadResult) . '; Error: ' . json_encode($uploadResult)], Response::HTTP_CONFLICT);
            }
        }

        return new JsonResponse('', Response::HTTP_CONFLICT);
    }

    /**
     * @Route("/sign", methods={"POST"})
     * @SWG\Post(
     *     summary="Sign files",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="files", type="array", items={"type": "object", "properties": {"uri": {"type": "string"}, "signedFile": {"type": "string"}, "creationTime": {"type": "integer"}, "expiryTime": {"type": "integer"}}}),
     *             @SWG\Property(property="feeWhole", type="integer"),
     *             @SWG\Property(property="feeFraction", type="integer")
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=406, description="Invalid arguments")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="File")
     * @param Request $request
     * @param Blockchain $blockChain
     * @return JsonResponse
     */
    public function signFile(Request $request, BlockChain $blockChain)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $files = $content['files'];
            $feeWhole = intval($content['feeWhole']);
            $feeFraction = intval($content['feeFraction']);
        } else {
            $files = $request->request->get('files');
            $feeWhole = intval($request->request->get('feeWhole'));
            $feeFraction = intval($request->request->get('feeFraction'));
        }

        //  get public key
        $publicKey = $account->getPublicKey();

        try {
            if (is_array($files)) {
                $duplicateFiles = [];
                foreach ($files as $file) {
                    $action = new File();
                    $action->setUri($file['uri']);
                    $action->addAuthorAddresses($publicKey);

                    //  Verify signature
                    $signatureResult = $blockChain->verifySignature($publicKey, $file['signedFile'], $action, $file['creationTime'], $file['expiryTime'], $feeWhole, $feeFraction);
                    if (!($signatureResult['signatureResult'] instanceof Done)) {
                        throw new Exception('Invalid signature for file: ' . $file['uri'] . '; Error type: ' . get_class($signatureResult['signatureResult']));
                    }

                    //  Broadcast
                    $broadcastResult = $blockChain->broadcast($signatureResult['transaction'], $publicKey, $file['signedFile']);
                    if (!($broadcastResult instanceof Done)) {
                        if ($broadcastResult instanceof UriError && $broadcastResult->getUriProblemType() === UriProblemType::duplicate) {
                            $fileEntity = $em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $file['uri']]);
                            if ($fileEntity) {
                                /**
                                 * @var Account $fileEntityAuthor
                                 */
                                $fileEntityAuthor = $fileEntity->getAuthor();
                                $duplicateFiles[$file['uri']] = ['publicKey' => $fileEntityAuthor->getPublicKey(), 'firstName' => $fileEntityAuthor->getFirstName(), 'lastName' => $fileEntityAuthor->getLastName()];
                            } else {
                                $duplicateFiles[$file['uri']] = '';
                            }
                        } elseif ($broadcastResult instanceof NotEnoughBalance) {
                            return new JsonResponse(['type' => 'story_not_enough_balance'], Response::HTTP_CONFLICT);
                        } else {
                            return new JsonResponse(['type' => 'system_error', 'msg' => 'Broadcasting failed for URI: ' . $file['uri'] . '; Error type: ' . get_class($broadcastResult)], Response::HTTP_CONFLICT);
                        }
                    }
                }

                if (count($duplicateFiles)) {
                    return new JsonResponse($duplicateFiles);
                } else {
                    return new JsonResponse('', Response::HTTP_NO_CONTENT);
                }
            }

            return new JsonResponse('', Response::HTTP_NOT_ACCEPTABLE);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/upload-content", methods={"POST"})
     * @SWG\Post(
     *     summary="Upload content file",
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
     * @SWG\Tag(name="File")
     * @param Request $request
     * @param BlockChain $blockChain
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadContentFile(Request $request, BlockChain $blockChain)
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

        $channelStorageEndpoint = $this->getParameter('channel_storage_endpoint');

        $uploadResult = $blockChain->uploadFile($content, 'text/html');
        if ($uploadResult instanceof StorageFileAddress) {
            return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri()]);
        } elseif ($uploadResult instanceof UriError) {
            if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri()]);
            } else {
                return new JsonResponse(['File upload error: ' . $uploadResult->getUriProblemType()], Response::HTTP_CONFLICT);
            }
        } else {
            return new JsonResponse(['Error type: ' . get_class($uploadResult) . '; Error: ' . json_encode($uploadResult)], Response::HTTP_CONFLICT);
        }
    }
}