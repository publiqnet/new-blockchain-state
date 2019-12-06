<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/19/19
 * Time: 4:33 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Draft;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DraftApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/draft")
 */
class DraftApiController extends Controller
{
    /**
     * @Route("/create", methods={"PUT"})
     * @SWG\Put(
     *     summary="Create draft",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="title", type="string"),
     *             @SWG\Property(property="headline", type="string"),
     *             @SWG\Property(property="content", type="string"),
     *             @SWG\Property(property="forAdults", type="boolean"),
     *             @SWG\Property(property="hideCover", type="boolean"),
     *             @SWG\Property(property="reference", type="string"),
     *             @SWG\Property(property="sourceOfMaterial", type="string"),
     *             @SWG\Property(property="contentUris", type="array", items={"type": "object"}),
     *             @SWG\Property(property="tags", type="array", items={"type": "object"}),
     *             @SWG\Property(property="options", type="array", items={"type": "object"}),
     *             @SWG\Property(property="publication", type="string"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @param Request $request
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function createDraft(Request $request)
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
            $contentArr = json_decode($content, true);

            $title = $contentArr['title'];
            $content = $contentArr['content'];
            if (isset($contentArr['headline'])) {
                $headline = $contentArr['headline'];
            }
            if (isset($contentArr['forAdults'])) {
                $forAdults = $contentArr['forAdults'];
            }
            if (isset($contentArr['hideCover'])) {
                $hideCover = $contentArr['hideCover'];
            }
            if (isset($contentArr['reference'])) {
                $reference = $contentArr['reference'];
            }
            if (isset($contentArr['sourceOfMaterial'])) {
                $sourceOfMaterial = $contentArr['sourceOfMaterial'];
            }
            if (isset($contentArr['contentUris'])) {
                $contentUris = $contentArr['contentUris'];
            }
            if (isset($contentArr['tags'])) {
                $tags = $contentArr['tags'];
            }
            if (isset($contentArr['options'])) {
                $options = $contentArr['options'];
            }
            if (isset($contentArr['publication'])) {
                $publication = $contentArr['publication'];
            }
        } else {
            $title = $request->request->get('title');
            $content = $request->request->get('content');
            $headline = $request->request->get('headline');
            $forAdults = $request->request->get('forAdults');
            $hideCover = $request->request->get('hideCover');
            $reference = $request->request->get('reference');
            $sourceOfMaterial = $request->request->get('sourceOfMaterial');
            $contentUris = $request->request->get('contentUris');
            $tags = $request->request->get('tags');
            $options = $request->request->get('options');
            $publication = $request->request->get('publication');
        }

        $draft = new Draft();

        try {
            $draft->setAccount($account);
            $draft->setTitle($title);
            $draft->setContent($content);
            if (isset($headline)) {
                $draft->setHeadline($headline);
            }
            if (isset($forAdults)) {
                $draft->setForAdults($forAdults);
            }
            if (isset($hideCover)) {
                $draft->setHideCover($hideCover);
            }
            if (isset($reference)) {
                $draft->setReference($reference);
            }
            if (isset($sourceOfMaterial)) {
                $draft->setSourceOfMaterial($sourceOfMaterial);
            }
            if (isset($contentUris) && count($contentUris) > 0) {
                $draft->setContentUris($contentUris);
            }
            if (isset($tags)) {
                $draft->setTags($tags);
            }
            if (isset($options)) {
                $draft->setOptions($options);
            }
            if (isset($publication)) {
                $draft->setPublication($publication);
            }

            $em->persist($draft);
            $em->flush();

            $draft = $this->get('serializer')->normalize($draft, null, ['groups' => ['draft']]);

            return new JsonResponse($draft);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/{id}", methods={"POST"})
     * @SWG\Post(
     *     summary="Update draft",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="title", type="string"),
     *             @SWG\Property(property="headline", type="string"),
     *             @SWG\Property(property="content", type="string"),
     *             @SWG\Property(property="forAdults", type="boolean"),
     *             @SWG\Property(property="hideCover", type="boolean"),
     *             @SWG\Property(property="reference", type="string"),
     *             @SWG\Property(property="sourceOfMaterial", type="string"),
     *             @SWG\Property(property="contentUris", type="array", items={"type": "object"}),
     *             @SWG\Property(property="tags", type="array", items={"type": "object"}),
     *             @SWG\Property(property="options", type="array", items={"type": "object"}),
     *             @SWG\Property(property="publication", type="string"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=403, description="Forbidden for user")
     * @SWG\Response(response=404, description="Draft not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @param Request $request
     * @param $id
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function updateDraft(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $draft = $em->getRepository(Draft::class)->find($id);
        if (!$draft) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        //  check if user has permission to update - only owner has
        if ($account !== $draft->getAccount()) {
            return new JsonResponse('', Response::HTTP_FORBIDDEN);
        }

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $contentArr = json_decode($content, true);

            $title = $contentArr['title'];
            $content = $contentArr['content'];
            if (isset($contentArr['headline'])) {
                $headline = $contentArr['headline'];
            }
            if (isset($contentArr['forAdults'])) {
                $forAdults = $contentArr['forAdults'];
            }
            if (isset($contentArr['hideCover'])) {
                $hideCover = $contentArr['hideCover'];
            }
            if (isset($contentArr['reference'])) {
                $reference = $contentArr['reference'];
            }
            if (isset($contentArr['sourceOfMaterial'])) {
                $sourceOfMaterial = $contentArr['sourceOfMaterial'];
            }
            if (isset($contentArr['contentUris'])) {
                $contentUris = $contentArr['contentUris'];
            }
            if (isset($contentArr['tags'])) {
                $tags = $contentArr['tags'];
            }
            if (isset($contentArr['options'])) {
                $options = $contentArr['options'];
            }
            if (isset($contentArr['publication'])) {
                $publication = $contentArr['publication'];
            }
        } else {
            $title = $request->request->get('title');
            $content = $request->request->get('content');
            $headline = $request->request->get('headline');
            $forAdults = $request->request->get('forAdults');
            $hideCover = $request->request->get('hideCover');
            $reference = $request->request->get('reference');
            $sourceOfMaterial = $request->request->get('sourceOfMaterial');
            $contentUris = $request->request->get('contentUris');
            $tags = $request->request->get('tags');
            $options = $request->request->get('options');
            $publication = $request->request->get('publication');
        }

        try {
            $draft->setTitle($title);
            $draft->setContent($content);
            if (isset($headline)) {
                $draft->setHeadline($headline);
            }
            if (isset($forAdults)) {
                $draft->setForAdults($forAdults);
            }
            if (isset($hideCover)) {
                $draft->setHideCover($hideCover);
            }
            if (isset($reference)) {
                $draft->setReference($reference);
            }
            if (isset($sourceOfMaterial)) {
                $draft->setSourceOfMaterial($sourceOfMaterial);
            }
            if (isset($contentUris)) {
                $draft->setContentUris($contentUris);
            }
            if (isset($tags)) {
                $draft->setTags($tags);
            }
            if (isset($options)) {
                $draft->setOptions($options);
            }
            if (isset($publication)) {
                $draft->setPublication($publication);
            }

            $em->persist($draft);
            $em->flush();

            $draft = $this->get('serializer')->normalize($draft, null, ['groups' => ['draft']]);

            return new JsonResponse($draft);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete draft",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @param $id
     * @return Response
     */
    public function deleteDraft($id)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $draft = $em->getRepository(Draft::class)->findOneBy(["id" => $id, "account" => $account]);
        if (!$draft) {
            return new JsonResponse(['message' => 'no_such_draft_associated_with_user'], Response::HTTP_CONFLICT);
        }

        try {
            $em->remove($draft);
            $em->flush();

            return new JsonResponse('', Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("s", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete all drafts",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @return Response
     */
    public function deleteAllDrafts()
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $drafts = $em->getRepository(Draft::class)->findBy(["account" => $account]);
        if ($drafts) {
            try {
                foreach ($drafts as $draft) {
                    $em->remove($draft);
                    $em->flush();
                }

                return new JsonResponse('', Response::HTTP_NO_CONTENT);
            } catch (\Exception $e) {
                return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
            }
        }

        return new JsonResponse('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get draft",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @param $id
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getDraft($id)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $draft = $em->getRepository(Draft::class)->findOneBy(["id" => $id, "account" => $account]);
        if (!$draft) {
            return new JsonResponse(['message' => 'no_such_draft_associated_with_user'], Response::HTTP_CONFLICT);
        }

        /**
         * @var \DateTime $created
         */
        $created = $draft->getCreated();
        $draft->setCreated($created->getTimestamp());

        /**
         * @var \DateTime $updated
         */
        $updated = $draft->getUpdated();
        $draft->setUpdated($updated->getTimestamp());

        $draft = $this->get('serializer')->normalize($draft, null, ['groups' => ['draft']]);

        return new JsonResponse($draft);
    }

    /**
     * @Route("s/{count}/{fromId}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get all drafts",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Draft")
     * @param int $count
     * @param $fromId
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getDrafts(int $count, $fromId)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $fromDraft = $em->getRepository(Draft::class)->find($fromId);

        $drafts = $em->getRepository(Draft::class)->getAuthorDrafts($account, $count + 1, $fromDraft);
        if ($drafts) {
            /**
             * @var Draft $draft
             */
            foreach ($drafts as $draft) {
                /**
                 * @var \DateTime $created
                 */
                $created = $draft->getCreated();
                $draft->setCreated($created->getTimestamp());

                /**
                 * @var \DateTime $updated
                 */
                $updated = $draft->getUpdated();
                $draft->setUpdated($updated->getTimestamp());
            }
        }
        $drafts = $this->get('serializer')->normalize($drafts, null, ['groups' => ['draftList', 'tag', 'accountBase', 'publication']]);

        $more = false;
        if (count($drafts) > $count) {
            unset($drafts[$count]);
            $more = true;
        }

        return new JsonResponse(['data' => $drafts, 'more' => $more]);
    }
}