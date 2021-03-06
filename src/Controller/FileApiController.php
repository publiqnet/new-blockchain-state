<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/11/19
 * Time: 1:10 PM
 */

namespace App\Controller;

use App\Custom\base58;
use App\Entity\Account;
use App\Entity\AccountFile;
use App\Entity\ContentUnit;
use App\Entity\Draft;
use App\Entity\DraftFile;
use App\Service\BlockChain;
use Doctrine\ORM\EntityManager;
use Exception;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\Done;
use PubliqAPI\Model\File;
use PubliqAPI\Model\InvalidSignature;
use PubliqAPI\Model\NotEnoughBalance;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\UriError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
class FileApiController extends AbstractController
{
    /**
     * @Route("s/{count}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get images",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="File")
     * @param int $count
     * @param string $fromUri
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function images(int $count, string $fromUri)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $backendEndpoint = $this->getParameter('backend_endpoint');

        $fromFile = null;
        if ($fromUri) {
            $fromFile = $em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fromUri]);
        }

        /**
         * @var \App\Entity\File[] $images
         */
        $images = $em->getRepository(\App\Entity\File::class)->getImages($count + 1, $fromFile);
        if ($images) {
            foreach ($images as $image) {
                /**
                 * @var ContentUnit[] $contentUnits
                 */
                $contentUnits = $image->getContentUnits();
                foreach ($contentUnits as $contentUnit) {
                    /**
                     * @var Account $channel
                     */
                    $channel = $contentUnit->getChannel();
                    if ($channel->getUrl()) {
                        break;
                    }
                }

                $image->setUrl($channel->getUrl() . '/storage?file=' . $image->getUri());

                if (!$image->getThumbnail()) {
                    $image->setThumbnail($image->getUrl());
                } else {
                    $image->setThumbnail($backendEndpoint . '/' . $image->getThumbnail());
                }
            }
        }
        $images = $this->get('serializer')->normalize($images, null, ['groups' => ['images', 'tag', 'accountBase']]);

        //  check if more content exist
        $more = false;
        if (count($images) > $count) {
            unset($images[$count]);
            $more = true;
        }

        return new JsonResponse(['data' => $images, 'more' => $more]);
    }

    /**
     * @Route("s-by-tag/{tag}/{count}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get images by tag",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="File")
     * @param string $tag
     * @param int $count
     * @param string $fromUri
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function imagesByTag(string $tag, int $count, string $fromUri)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $backendEndpoint = $this->getParameter('backend_endpoint');

        $fromFile = null;
        if ($fromUri) {
            $fromFile = $em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fromUri]);
        }

        /**
         * @var \App\Entity\File[] $images
         */
        $images = $em->getRepository(\App\Entity\File::class)->getImagesByTag($tag, $count + 1, $fromFile);
        if ($images) {
            foreach ($images as $image) {
                /**
                 * @var ContentUnit[] $contentUnits
                 */
                $contentUnits = $image->getContentUnits();
                foreach ($contentUnits as $contentUnit) {
                    /**
                     * @var Account $channel
                     */
                    $channel = $contentUnit->getChannel();
                    if ($channel->getUrl()) {
                        break;
                    }
                }

                $image->setUrl($channel->getUrl() . '/storage?file=' . $image->getUri());

                if (!$image->getThumbnail()) {
                    $image->setThumbnail($image->getUrl());
                } else {
                    $image->setThumbnail($backendEndpoint . '/' . $image->getThumbnail());
                }
            }
        }
        $images = $this->get('serializer')->normalize($images, null, ['groups' => ['images', 'tag', 'accountBase']]);

        //  check if more content exist
        $more = false;
        if (count($images) > $count) {
            unset($images[$count]);
            $more = true;
        }

        return new JsonResponse(['data' => $images, 'more' => $more]);
    }

    /**
     * @Route("/upload", methods={"POST"})
     * @SWG\Post(
     *     summary="Upload file",
     *     consumes={"multipart/form-data"},
     *     @SWG\Parameter(name="replacementUri", in="formData", type="string", description="Replacement URI"),
     *     @SWG\Parameter(name="file", in="formData", type="file", description="File"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string"),
     * )
     * @SWG\Response(response=200, description="successfully uploaded")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="something went wrong")
     * @SWG\Tag(name="File")
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function uploadFile(Request $request)
    {
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $replacementUri = $content['replacementUri'];
        } else {
            $replacementUri = $request->request->get('replacementUri');
        }

        $backendEndpoint = $this->getParameter('backend_endpoint');
        $draftPath = $this->getParameter('draft_path');

        /**
         * @var UploadedFile $file
         */
        $file = $request->files->get('file');
        if ($file && $file->getClientMimeType()) {
            $fileData = file_get_contents($file->getRealPath());
            $fileDataHash = hash('sha256', $fileData);
            $fileUri = $fileUri = base58::Encode($fileDataHash);

            //  move file to draft files directory
            $fileName = $fileUri . '.' . $file->guessExtension();
            $file->move($draftPath, $fileName);

            return new JsonResponse(['uri' => $fileUri, 'link' => $backendEndpoint . '/' . $draftPath . '/' . $fileName, 'url' => $backendEndpoint . '/' . $draftPath . '/' . $fileName]);
        }

        /**
         * @var UploadedFile $file
         */
        $file = $request->files->get('upload');
        if ($file && $file->getClientMimeType()) {
            $fileData = file_get_contents($file->getRealPath());
            $fileDataHash = hash('sha256', $fileData);
            $fileUri = $fileUri = base58::Encode($fileDataHash);

            //  move file to draft files directory
            $fileName = $fileUri . '.' . $file->guessExtension();
            $file->move($draftPath, $fileName);

            return new JsonResponse(['uri' => $fileUri, 'link' => $backendEndpoint . '/' . $draftPath . '/' . $fileName, 'url' => $backendEndpoint . '/' . $draftPath . '/' . $fileName]);
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
     *             @SWG\Property(property="feeFraction", type="integer"),
     *             @SWG\Property(property="draftId", type="integer")
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
        /**
         * @var EntityManager $em
         */
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
            $draftId = $content['draftId'];
        } else {
            $files = $request->request->get('files');
            $feeWhole = intval($request->request->get('feeWhole'));
            $feeFraction = intval($request->request->get('feeFraction'));
            $draftId = intval($request->request->get('draftId'));
        }

        //  get public key
        $publicKey = $account->getPublicKey();
        $draft = $em->getRepository(Draft::class)->find($draftId);

        try {
            if (is_array($files)) {
                $duplicateFiles = [];
                foreach ($files as $file) {
                    $fileEntity = $em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $file['uri']]);
                    if ($fileEntity) {
                        /**
                         * @var AccountFile[] $fileEntityAuthors
                         */
                        $fileEntityAuthors = $fileEntity->getAuthors();
                        foreach ($fileEntityAuthors as $fileEntityAuthor) {
                            $duplicateFiles[$file['uri']] = ['publicKey' => $fileEntityAuthor->getAccount()->getPublicKey(), 'firstName' => $fileEntityAuthor->getAccount()->getFirstName(), 'lastName' => $fileEntityAuthor->getAccount()->getLastName()];
                        }

                        //  upload file to channel storage if file has no articles in current channel
                        $fileContentUnits = $fileEntity->getContentUnits();
                        if (count($fileContentUnits) == 0) {
                            //  get file local path
                            $draftFile = $em->getRepository(DraftFile::class)->findOneBy(['draft' => $draft, 'uri' => $file['uri']]);
                            if ($draftFile) {
                                $fileObj = new \Symfony\Component\HttpFoundation\File\File($draftFile->getPath());
                                $fileData = file_get_contents($fileObj->getRealPath());

                                //  upload file into channel storage
                                $uploadResult = $blockChain->uploadFile($fileData, $fileObj->getMimeType());
                                if ($uploadResult instanceof StorageFileAddress) {
                                    if (!$fileEntity->getMimeType()) {
                                        $fileEntity->setMimeType($fileObj->getMimeType());
                                        $em->persist($fileEntity);
                                        $em->flush();
                                    }
                                } elseif ($uploadResult instanceof UriError) {
                                    if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                                        if (!$fileEntity->getMimeType()) {
                                            $fileEntity->setMimeType($fileObj->getMimeType());
                                            $em->persist($fileEntity);
                                            $em->flush();
                                        }
                                    } else {
                                        throw new Exception('File upload error: ' . $uploadResult->getUriProblemType());
                                    }
                                } else {
                                    throw new Exception('Error type: ' . get_class($uploadResult));
                                }
                            }
                        }

                        continue;
                    }

                    //  get file local path
                    $draftFile = $em->getRepository(DraftFile::class)->findOneBy(['draft' => $draft, 'uri' => $file['uri']]);
                    if ($draftFile) {
                        $fileObj = new \Symfony\Component\HttpFoundation\File\File($draftFile->getPath());
                        $fileData = file_get_contents($fileObj->getRealPath());

                        //  upload file into channel storage
                        $uploadResult = $blockChain->uploadFile($fileData, $fileObj->getMimeType());
                        if ($uploadResult instanceof StorageFileAddress) {

                        } elseif ($uploadResult instanceof UriError) {
                            if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {

                            } else {
                                throw new Exception('File upload error: ' . $uploadResult->getUriProblemType());
                            }
                        } else {
                            throw new Exception('Error type: ' . get_class($uploadResult));
                        }
                    }

                    //  Verify signature
                    $action = new File();
                    $action->setUri($file['uri']);
                    $action->addAuthorAddresses($publicKey);

                    $signatureResult = $blockChain->verifySignature($publicKey, $file['signedFile'], $action, $file['creationTime'], $file['expiryTime'], $feeWhole, $feeFraction);
                    if ($signatureResult['signatureResult'] instanceof InvalidSignature) {
                        return new JsonResponse(['type' => 'file_invalid_signature'], Response::HTTP_CONFLICT);
                    } elseif ($signatureResult['signatureResult'] instanceof UriError) {
                        /**
                         * @var UriError $uriError
                         */
                        $uriError = $signatureResult['signatureResult'];
                        if ($uriError->getUriProblemType() !== UriProblemType::duplicate) {
                            throw new Exception('Invalid file URI: ' . $uriError->getUri() . '(' . $uriError->getUriProblemType() . ')');
                        }
                    } elseif (!($signatureResult['signatureResult'] instanceof Done)) {
                        throw new Exception('Error for file: ' . $file['uri'] . '; Error type: ' . get_class($signatureResult['signatureResult']));
                    }

                    //  Broadcast
                    $broadcastResult = $blockChain->broadcast($signatureResult['transaction'], $publicKey, $file['signedFile']);
                    if (!($broadcastResult instanceof Done)) {
                        if ($broadcastResult instanceof UriError && $broadcastResult->getUriProblemType() === UriProblemType::duplicate) {
                            $fileEntity = $em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $file['uri']]);
                            if ($fileEntity) {
                                /**
                                 * @var AccountFile[] $fileEntityAuthors
                                 */
                                $fileEntityAuthors = $fileEntity->getAuthors();
                                foreach ($fileEntityAuthors as $fileEntityAuthor) {
                                    $duplicateFiles[$file['uri']] = ['publicKey' => $fileEntityAuthor->getAccount()->getPublicKey(), 'firstName' => $fileEntityAuthor->getAccount()->getFirstName(), 'lastName' => $fileEntityAuthor->getAccount()->getLastName()];
                                }
                            } else {
                                $duplicateFiles[$file['uri']] = '';
                            }
                        } elseif ($broadcastResult instanceof NotEnoughBalance) {
                            return new JsonResponse(['type' => 'story_not_enough_balance'], Response::HTTP_CONFLICT);
                        } elseif ($broadcastResult instanceof InvalidSignature) {
                            return new JsonResponse(['type' => 'story_invalid_signature'], Response::HTTP_CONFLICT);
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
     *             @SWG\Property(property="key", type="integer"),
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
        $key = null;
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            if (isset($content['key'])) {
                $key = $content['key'];
            }
            $content = $content['content'];
        } else {
            $content = $request->request->get('content');
            $key = $request->request->get('key');
        }

        if (!$content) {
            return new JsonResponse(['message' => 'Empty content'], Response::HTTP_CONFLICT);
        }

        $channelStorageEndpoint = $this->getParameter('channel_storage_endpoint');

        $uploadResult = $blockChain->uploadFile($content, 'text/html');
        if ($uploadResult instanceof StorageFileAddress) {
            return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri(), 'key' => $key]);
        } elseif ($uploadResult instanceof UriError) {
            if ($uploadResult->getUriProblemType() === UriProblemType::duplicate) {
                return new JsonResponse(['uri' => $uploadResult->getUri(), 'link' => $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri(), 'key' => $key]);
            } else {
                return new JsonResponse(['File upload error: ' . $uploadResult->getUriProblemType()], Response::HTTP_CONFLICT);
            }
        } else {
            return new JsonResponse(['Error type: ' . get_class($uploadResult) . '; Error: ' . json_encode($uploadResult)], Response::HTTP_CONFLICT);
        }
    }
}