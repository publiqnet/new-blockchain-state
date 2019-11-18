<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/18/19
 * Time: 12:10 PM
 */

namespace App\Controller;

use App\Entity\Block;
use App\Entity\ContentUnit;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TempApiController
 * @package App\Controller
 *
 * @Route("/api/temp")
 */
class TempApiController extends Controller
{
    /**
     * @Route("/block/{number}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get block hash by number",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="Temp")
     * @param int $number
     * @return JsonResponse
     */
    public function getBlockHashByNumber(int $number)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Block $block
         */
        $block = $em->getRepository(Block::class)->findOneBy(['number' => $number]);
        if (!$block) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['hash' => $block->getHash()]);
    }

    /**
     * @Route("/article/{uri}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get article views history",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="Temp")
     * @param string $uri
     * @return JsonResponse
     */
    public function getArticleViewsHistory(string $uri)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var ContentUnit $contentUnit
         */
        $contentUnit = $em->getRepository(ContentUnit::class)->findOneBy(['uri' => $uri]);
        if (!$contentUnit) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $contentUnitViews = $contentUnit->getViewsPerChannel();
        $contentUnitViews = $this->get('serializer')->normalize($contentUnitViews, null, ['groups' => ['contentUnitViews', 'explorerBlockLight', 'explorerAccountLight']]);

        return new JsonResponse($contentUnitViews);
    }
}
