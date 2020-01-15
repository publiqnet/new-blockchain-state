<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/6/19
 * Time: 5:43 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Tag;
use App\Service\ContentUnit as CUService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TagApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/tag")
 */
class TagApiController extends Controller
{
    /**
     * @Route("s", methods={"GET"})
     * @SWG\Get(
     *     summary="Get all Tags",
     *     consumes={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tag")
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getTags()
    {
        $em = $this->getDoctrine()->getManager();

        $tags = $em->getRepository(Tag::class)->getTagsByPopularity(40);
        $tags = $this->get('serializer')->normalize($tags, null, ['groups' => ['tag']]);

        return new JsonResponse($tags);
    }

    /**
     * @Route("/{tag}/articles/{count}/{boostedCount}/{fromUri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get Articles by Tag",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=false, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Tag")
     * @param string $tag
     * @param int $count
     * @param int $boostedCount
     * @param string $fromUri
     * @param CUService $contentUnitService
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getArticlesByTag(string $tag, int $count, int $boostedCount, string $fromUri, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $tag = $em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
        if (!$tag) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $fromContentUnit = null;
        if ($fromUri) {
            $fromContentUnit = $em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $fromUri]);
        }

        $contentUnits = $em->getRepository(\App\Entity\ContentUnit::class)->getArticlesByTag($tag,$count + 1, $fromContentUnit);

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
                $boostedContentUnits = $contentUnitService->prepare($boostedContentUnits, true, $account);
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

        $contentUnits = $contentUnitService->prepareTags($contentUnits);

        return new JsonResponse(['data' => $contentUnits, 'more' => $more]);
    }
}