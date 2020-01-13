<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/23/19
 * Time: 6:35 PM
 */

namespace App\Controller;

use App\Entity\Block;
use App\Entity\NetworkHomeContent;
use App\Entity\NetworkHomeSlider;
use App\Entity\NetworkPage;
use App\Entity\NetworkPbqContent;
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
     * @Route("/status", methods={"GET"}, name="network_status")
     * @SWG\Get(
     *     summary="Get rewards stats per day / week / month / lifetime for given type",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     */
    public function getTopRewards()
    {
        $em = $this->getDoctrine()->getManager();

        $rewards = [];
        $miners = [];

        //  REWARDS
        $rewardTypes = ['author', 'channel', 'miner', 'storage'];
        foreach ($rewardTypes as $rewardType) {
            $timezone = new \DateTimeZone('UTC');
            $date = new \DateTime();
            $date->setTimezone($timezone);

            //  lifetime
            $rewards[$rewardType]['lifetime'] = $em->getRepository(Reward::class)->getTopRewardsByType($rewardType);

            //  last month
            $date->modify('-1 month');
            $rewards[$rewardType]['lastMonth'] = $em->getRepository(Reward::class)->getTopRewardsByType($rewardType, $date->getTimestamp());

            //  last week
            $date->modify('+1 month');
            $date->modify('-1 week');
            $rewards[$rewardType]['lastWeel'] = $em->getRepository(Reward::class)->getTopRewardsByType($rewardType, $date->getTimestamp());

            //  last day
            $date->modify('+1 week');
            $date->modify('-1 day');
            $rewards[$rewardType]['lastDay'] = $em->getRepository(Reward::class)->getTopRewardsByType($rewardType, $date->getTimestamp());
        }

        //  MINERS
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  lifetime
        $miners['lifetime'] = $em->getRepository(Block::class)->getMiners();

        //  last month
        $date->modify('-1 month');
        $miners['lastMonth'] = $em->getRepository(Block::class)->getMiners($date->getTimestamp());

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $miners['lastWeek'] = $em->getRepository(Block::class)->getMiners($date->getTimestamp());

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $miners['lastDay'] = $em->getRepository(Block::class)->getMiners($date->getTimestamp());

        //  PBQ TOTAL SUPPLY
        //  get last block
        $lastBlock = $em->getRepository(Block::class)->findOneBy([], ['id' => 'DESC']);

        return new JsonResponse([
            'rewards' => $rewards,
            'miners' => $miners,
            'supply' => [
                'issued' => ['whole' => 250000000 + $lastBlock->getNumber() * 1000, 'fraction' => 0],
                'scheduled' => ['whole' => 500000000, 'fraction' => 0],
            ]
        ]);
    }

    /**
     * @Route("/homepage", methods={"GET"}, name="network_homepage")
     * @SWG\Get(
     *     summary="Get Homepage data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
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
     * @SWG\Tag(name="Network")
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

    /**
     * @Route("/page/pbq", methods={"GET"}, name="network_page_pbq")
     * @SWG\Get(
     *     summary="Get PBQ page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPagePbq()
    {
        $em = $this->getDoctrine()->getManager();
        $networkFilePath = $this->getParameter('network_file_path');

        $pagePbq = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'pbq']);
        if ($pagePbq) {
            $pagePbq = $this->get('serializer')->normalize($pagePbq, null, ['groups' => ['networkPage']]);

            /**
             * @var NetworkPbqContent[] $pbqContents
             */
            $pbqContents = $em->getRepository(NetworkPbqContent::class)->findAll();
            if ($pbqContents) {
                foreach ($pbqContents as $pbqContent) {
                    if ($pbqContent->getImage()) {
                        $pbqContent->setImage($networkFilePath . '/' . $pbqContent->getImage());
                    }
                    if ($pbqContent->getImageHover()) {
                        $pbqContent->setImageHover($networkFilePath . '/' . $pbqContent->getImageHover());
                    }
                }
            }
            $pbqContents = $this->get('serializer')->normalize($pbqContents, null, ['groups' => ['networkPbqContent']]);

            return new JsonResponse(['main' => $pagePbq, 'contents' => $pbqContents]);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}