<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/23/19
 * Time: 6:35 PM
 */

namespace App\Controller;

use App\Entity\NetworkHomeContent;
use App\Entity\NetworkHomeSlider;
use App\Entity\NetworkSupportContent;
use App\Entity\Reward;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class NetworkApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/network")
 */
class NetworkApiController extends Controller
{
    /**
     * @Route("/rewards/{type}", methods={"GET"}, name="network_top_rewards")
     * @SWG\Get(
     *     summary="Get rewards stats per day / week / month / lifetime for given type",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network / Rewards")
     * @param string $type
     * @return JsonResponse
     */
    public function getTopRewards(string $type)
    {
        $em = $this->getDoctrine()->getManager();

        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  lifetime
        $rewardSummaryLifetime = $em->getRepository(Reward::class)->getTopRewardsByType($type);

        //  last month
        $date->modify('-1 month');
        $rewardSummaryMonth = $em->getRepository(Reward::class)->getTopRewardsByType($type, $date->getTimestamp());

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $rewardSummaryWeek = $em->getRepository(Reward::class)->getTopRewardsByType($type, $date->getTimestamp());

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $rewardSummaryDay = $em->getRepository(Reward::class)->getTopRewardsByType($type, $date->getTimestamp());

        return new JsonResponse(['lifetime' => $rewardSummaryLifetime, 'month' => $rewardSummaryMonth, 'week' => $rewardSummaryWeek, 'day' => $rewardSummaryDay]);
    }

    /**
     * @Route("/homepage", methods={"GET"}, name="network_homepage")
     * @SWG\Get(
     *     summary="Get Homepage data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network / Home")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getHomeContent()
    {
        $em = $this->getDoctrine()->getManager();

        $homeContent = $em->getRepository(NetworkHomeContent::class)->findAll();
        $homeContent = $this->get('serializer')->normalize($homeContent, null, ['groups' => ['networkHomeContent']]);

        $homeSlider = $em->getRepository(NetworkHomeSlider::class)->findAll();
        $homeSlider = $this->get('serializer')->normalize($homeSlider, null, ['groups' => ['networkHomeSlider']]);

        return new JsonResponse(['content' => $homeContent, 'slider' => $homeSlider]);
    }

    /**
     * @Route("/support/{slug}", methods={"GET"}, name="network_support")
     * @SWG\Get(
     *     summary="Get Support data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network / Support")
     * @param $slug
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getSupportContent($slug)
    {
        $em = $this->getDoctrine()->getManager();

        $supportContent = $em->getRepository(NetworkSupportContent::class)->findOneBy(['slug' => $slug]);
        if ($supportContent) {
            $supportContent = $this->get('serializer')->normalize($supportContent, null, ['groups' => ['networkSupportContent']]);

            return new JsonResponse($supportContent);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}