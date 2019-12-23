<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/23/19
 * Time: 6:35 PM
 */

namespace App\Controller;

use App\Entity\Reward;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}