<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/23/19
 * Time: 6:35 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Block;
use App\Entity\NetworkBrandAssetsContent;
use App\Entity\NetworkBrandColourContent;
use App\Entity\NetworkBrandCommunicationContent;
use App\Entity\NetworkBrandLogoContent;
use App\Entity\NetworkBrandTypographyContent;
use App\Entity\NetworkDocsContent;
use App\Entity\NetworkFeedback;
use App\Entity\NetworkHomeContent;
use App\Entity\NetworkHomeSlider;
use App\Entity\NetworkPage;
use App\Entity\NetworkPbqContent;
use App\Entity\NetworkPubliqContent;
use App\Entity\NetworkShowcaseProject;
use App\Entity\NetworkSupportContent;
use App\Form\NetworkFeedbackType;
use App\Service\Custom;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTopRewards()
    {
        $em = $this->getDoctrine()->getManager();

        $rewards = [];
        $miners = [];
        $channels = [];
        $nodes = [];
        $storages = [];

        //  REWARDS ///////////////////////////////////////////////////////////////////////////////////////
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  last month
        $date->modify('-1 month');
        $rewardsRes = $em->getRepository(Account::class)->getTotalRewardSummary($date->getTimestamp());
        if ($rewardsRes) {
            foreach ($rewardsRes as $rewardsResSingle) {
                $whole = $rewardsResSingle->getTotalWhole();
                $fraction = $rewardsResSingle->getTotalFraction();

                while ($fraction > 99999999) {
                    $whole++;
                    $fraction -= 100000000;
                }

                $rewardsResSingle->setTotalWhole($whole);
                $rewardsResSingle->setTotalFraction($fraction);
            }
        }
        $rewards['lastMonth'] = $this->get('serializer')->normalize($rewardsRes, null, ['groups' => ['networkAccountReward']]);

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $rewardsRes = $em->getRepository(Account::class)->getTotalRewardSummary($date->getTimestamp());
        if ($rewardsRes) {
            foreach ($rewardsRes as $rewardsResSingle) {
                $whole = $rewardsResSingle->getTotalWhole();
                $fraction = $rewardsResSingle->getTotalFraction();

                while ($fraction > 99999999) {
                    $whole++;
                    $fraction -= 100000000;
                }

                $rewardsResSingle->setTotalWhole($whole);
                $rewardsResSingle->setTotalFraction($fraction);
            }
        }
        $rewards['lastWeek'] = $this->get('serializer')->normalize($rewardsRes, null, ['groups' => ['networkAccountReward']]);

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $rewardsRes = $em->getRepository(Account::class)->getTotalRewardSummary($date->getTimestamp());
        if ($rewardsRes) {
            foreach ($rewardsRes as $rewardsResSingle) {
                $whole = $rewardsResSingle->getTotalWhole();
                $fraction = $rewardsResSingle->getTotalFraction();

                while ($fraction > 99999999) {
                    $whole++;
                    $fraction -= 100000000;
                }

                $rewardsResSingle->setTotalWhole($whole);
                $rewardsResSingle->setTotalFraction($fraction);
            }
        }
        $rewards['lastDay'] = $this->get('serializer')->normalize($rewardsRes, null, ['groups' => ['networkAccountReward']]);

        //  MINERS  ///////////////////////////////////////////////////////////////////////////////////////
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

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

        //  CHANNELS    ///////////////////////////////////////////////////////////////////////////////////////
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  last month
        $date->modify('-1 month');
        $channelsRes = $em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $contributors = $em->getRepository(Account::class)->getChannelContributorsCount($channelsResSingle, $date->getTimestamp());
            $channelsResSingle->setContributorsCount($contributors['contributorsCount']);
        }
        $channels['lastMonth'] = $this->get('serializer')->normalize($channelsRes, null, ['groups' => ['networkAccountLight', 'networkAccountChannel']]);

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $channelsRes = $em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $contributors = $em->getRepository(Account::class)->getChannelContributorsCount($channelsResSingle, $date->getTimestamp());
            $channelsResSingle->setContributorsCount($contributors['contributorsCount']);
        }
        $channels['lastWeek'] = $this->get('serializer')->normalize($channelsRes, null, ['groups' => ['networkAccountLight', 'networkAccountChannel']]);

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $channelsRes = $em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $contributors = $em->getRepository(Account::class)->getChannelContributorsCount($channelsResSingle, $date->getTimestamp());
            $channelsResSingle->setContributorsCount($contributors['contributorsCount']);
        }
        $channels['lastDay'] = $this->get('serializer')->normalize($channelsRes, null, ['groups' => ['networkAccountLight', 'networkAccountChannel']]);

        //  STORAGE ///////////////////////////////////////////////////////////////////////////////////////
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  last month
        $date->modify('-1 month');
        $storageRes = $em->getRepository(Account::class)->getStorageSummary($date->getTimestamp());
        $storages['lastMonth'] = $this->get('serializer')->normalize($storageRes, null, ['groups' => ['networkAccountLight', 'networkAccountStorage']]);

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $storageRes = $em->getRepository(Account::class)->getStorageSummary($date->getTimestamp());
        $storages['lastWeek'] = $this->get('serializer')->normalize($storageRes, null, ['groups' => ['networkAccountLight', 'networkAccountStorage']]);

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $storageRes = $em->getRepository(Account::class)->getStorageSummary($date->getTimestamp());
        $storages['lastDay'] = $this->get('serializer')->normalize($storageRes, null, ['groups' => ['networkAccountLight', 'networkAccountStorage']]);

        //  ACTIVE NODES    ///////////////////////////////////////////////////////////////////////////////////////
        $activeChannels = $em->getRepository(Account::class)->getActiveNodes('channel');
        if ($activeChannels) {
            $activeChannels = $this->get('serializer')->normalize($activeChannels, null, ['groups' => ['networkAccountLight']]);
            foreach ($activeChannels as $activeChannel) {
                $activeChannel['type'] = 'channel';
                $nodes[] = $activeChannel;
            }
        }


        $activeStorages = $em->getRepository(Account::class)->getActiveNodes('storage');
        if ($activeStorages) {
            $activeStorages = $this->get('serializer')->normalize($activeStorages, null, ['groups' => ['networkAccountLight']]);
            foreach ($activeStorages as $activeStorage) {
                $activeStorage['type'] = 'storage';
                $nodes[] = $activeStorage;
            }
        }

        $activeMiners = $em->getRepository(Account::class)->getActiveMiners();
        if ($activeMiners) {
            $activeMiners = $this->get('serializer')->normalize($activeMiners, null, ['groups' => ['networkAccountLight']]);
            foreach ($activeMiners as $activeMiner) {
                $activeMiner['type'] = 'miner';
                $nodes[] = $activeMiner;
            }
        }

        return new JsonResponse([
            'rewards' => $rewards,
            'miners' => $miners,
            'channels' => $channels,
            'storages' => $storages,
            'activeNodes' => $nodes,
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

    /**
     * @Route("/page/publiq", methods={"GET"}, name="network_page_publiq")
     * @SWG\Get(
     *     summary="Get PUBLIQ page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPagePubliq()
    {
        $em = $this->getDoctrine()->getManager();
        $networkFilePath = $this->getParameter('network_file_path');

        $pagePubliq = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'publiq']);
        $pagePubliq = $this->get('serializer')->normalize($pagePubliq, null, ['groups' => ['networkPage']]);

        $pagePubliqDaemon = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'publiq_daemon']);
        $pagePubliqDaemon = $this->get('serializer')->normalize($pagePubliqDaemon, null, ['groups' => ['networkPage']]);

        $pagePubliqDaemonMainnet = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'publiq_daemon_mainnet']);
        $pagePubliqDaemonMainnet = $this->get('serializer')->normalize($pagePubliqDaemonMainnet, null, ['groups' => ['networkPage', 'networkPageDaemon']]);

        $pagePubliqDaemonTestnet = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'publiq_daemon_testnet']);
        $pagePubliqDaemonTestnet = $this->get('serializer')->normalize($pagePubliqDaemonTestnet, null, ['groups' => ['networkPage', 'networkPageDaemon']]);

        /**
         * @var NetworkPbqContent[] $pbqContents
         */
        $publiqContents = $em->getRepository(NetworkPubliqContent::class)->findAll();
        if ($publiqContents) {
            foreach ($publiqContents as $publiqContent) {
                if ($publiqContent->getImage()) {
                    $publiqContent->setImage($networkFilePath . '/' . $publiqContent->getImage());
                }
                if ($publiqContent->getImageHover()) {
                    $publiqContent->setImageHover($networkFilePath . '/' . $publiqContent->getImageHover());
                }
            }
        }
        $publiqContents = $this->get('serializer')->normalize($publiqContents, null, ['groups' => ['networkPubliqContent']]);

        return new JsonResponse(['main' => $pagePubliq, 'daemon' => $pagePubliqDaemon, 'mainnet' => $pagePubliqDaemonMainnet, 'testnet' => $pagePubliqDaemonTestnet, 'contents' => $publiqContents]);
    }

    /**
     * @Route("/page/showcase", methods={"GET"}, name="network_page_showcase")
     * @SWG\Get(
     *     summary="Get Showcase page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPageShowcase()
    {
        $em = $this->getDoctrine()->getManager();

        $pageShowcase = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'showcase']);
        $pageShowcase = $this->get('serializer')->normalize($pageShowcase, null, ['groups' => ['networkPage']]);

        $pageShowcaseProjects = $em->getRepository(NetworkShowcaseProject::class)->findAll();
        $pageShowcaseProjects = $this->get('serializer')->normalize($pageShowcaseProjects, null, ['groups' => ['networkShowcaseProject']]);

        return new JsonResponse(['main' => $pageShowcase, 'projects' => $pageShowcaseProjects]);
    }

    /**
     * @Route("/page/brand", methods={"GET"}, name="network_page_brand")
     * @SWG\Get(
     *     summary="Get Brand page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPageBrand()
    {
        $em = $this->getDoctrine()->getManager();
        $networkFilePath = $this->getParameter('network_file_path');

        $logoContentsReturn = [];
        $colourContentsReturn = [];
        $typographyContentsReturn = [];
        $communicationContentsReturn = [];
        $assetsContentsReturn = [];

        $pageBrand = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'brand']);
        $pageBrand = $this->get('serializer')->normalize($pageBrand, null, ['groups' => ['networkPage']]);

        $logoContents = $em->getRepository(NetworkBrandLogoContent::class)->findAll();
        if ($logoContents) {
            foreach ($logoContents as $logoContent) {
                if ($logoContent->getImage()) {
                    $logoContent->setImage($networkFilePath . '/' . $logoContent->getImage());
                }
            }
            $logoContents = $this->get('serializer')->normalize($logoContents, null, ['groups' => ['networkBrandContent']]);

            for ($i=0; $i<count($logoContents); $i++) {
                $logoContentsReturn[$logoContents[$i]['slug']] = $logoContents[$i];
            }
        }

        $colourContents = $em->getRepository(NetworkBrandColourContent::class)->findAll();
        if ($colourContents) {
            foreach ($colourContents as $colourContent) {
                if ($colourContent->getImage()) {
                    $colourContent->setImage($networkFilePath . '/' . $colourContent->getImage());
                }
            }
            $colourContents = $this->get('serializer')->normalize($colourContents, null, ['groups' => ['networkBrandContent']]);

            for ($i=0; $i<count($colourContents); $i++) {
                $colourContentsReturn[$colourContents[$i]['slug']] = $colourContents[$i];
            }
        }

        $typographyContents = $em->getRepository(NetworkBrandTypographyContent::class)->findAll();
        if ($typographyContents) {
            foreach ($typographyContents as $typographyContent) {
                if ($typographyContent->getImage()) {
                    $typographyContent->setImage($networkFilePath . '/' . $typographyContent->getImage());
                }
            }
            $typographyContents = $this->get('serializer')->normalize($typographyContents, null, ['groups' => ['networkBrandContent']]);

            for ($i=0; $i<count($typographyContents); $i++) {
                $typographyContentsReturn[$typographyContents[$i]['slug']] = $typographyContents[$i];
            }
        }

        $communicationContents = $em->getRepository(NetworkBrandCommunicationContent::class)->findAll();
        if ($communicationContents) {
            foreach ($communicationContents as $communicationContent) {
                if ($communicationContent->getImage()) {
                    $communicationContent->setImage($networkFilePath . '/' . $communicationContent->getImage());
                }
            }
            $communicationContents = $this->get('serializer')->normalize($communicationContents, null, ['groups' => ['networkBrandContent']]);

            for ($i=0; $i<count($communicationContents); $i++) {
                $communicationContentsReturn[$communicationContents[$i]['slug']] = $communicationContents[$i];
            }
        }

        $assetsContents = $em->getRepository(NetworkBrandAssetsContent::class)->findAll();
        if ($assetsContents) {
            foreach ($assetsContents as $assetsContent) {
                if ($assetsContent->getImage()) {
                    $assetsContent->setImage($networkFilePath . '/' . $assetsContent->getImage());
                }
            }
            $assetsContents = $this->get('serializer')->normalize($assetsContents, null, ['groups' => ['networkBrandContent']]);

            for ($i=0; $i<count($assetsContents); $i++) {
                $assetsContentsReturn[$assetsContents[$i]['slug']] = $assetsContents[$i];
            }
        }

        return new JsonResponse([
            'main' => $pageBrand,
            'logo' => $logoContentsReturn,
            'colour' => $colourContentsReturn,
            'typography' => $typographyContentsReturn,
            'communication' => $communicationContentsReturn,
            'assets' => $assetsContentsReturn,
        ]);
    }

    /**
     * @Route("/page/docs", methods={"GET"}, name="network_page_docs")
     * @SWG\Get(
     *     summary="Get Docs page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPageDocs()
    {
        $em = $this->getDoctrine()->getManager();

        $pageDocs = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'docs']);
        $pageDocs = $this->get('serializer')->normalize($pageDocs, null, ['groups' => ['networkPage']]);

        $contents = $em->getRepository(NetworkDocsContent::class)->findAll();
        $contents = $this->get('serializer')->normalize($contents, null, ['groups' => ['networkDocsContent']]);

        return new JsonResponse([
            'main' => $pageDocs,
            'contents' => $contents,
        ]);
    }

    /**
     * @Route("/page/contacts", methods={"GET"}, name="network_page_contacts")
     * @SWG\Get(
     *     summary="Get Contacts page data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Network")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getPageContacts()
    {
        $em = $this->getDoctrine()->getManager();

        $pageContacts = $em->getRepository(NetworkPage::class)->findOneBy(['slug' => 'contacts']);
        $pageContacts = $this->get('serializer')->normalize($pageContacts, null, ['groups' => ['networkPage']]);

        return new JsonResponse([
            'main' => $pageContacts,
            'captchaKey' => $this->getParameter('recaptcha_site_key')
        ]);
    }

    /**
     * @Route("/feedback", methods={"POST"}, name="network_form_feedback")
     * @SWG\Parameter(
     *     name="issue",
     *     in="body",
     *     @Model(type=NetworkFeedbackType::class)
     * )
     * @SWG\Response(
     *     response=201,
     *     description="send user issue to email"
     * )
     * @SWG\Tag(name="Network")
     *
     * @param Request $request
     * @param Custom $customService
     * @return Response
     */
    public function submitFeedback(Request $request, Custom $customService)
    {
        $em = $this->getDoctrine()->getManager();

        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $request->request->add(['network_feedback' => json_decode($content, true)]);
        }

        $feedback = new NetworkFeedback();

        $form = $this->createForm(NetworkFeedbackType::class, $feedback);
        $form->handleRequest($request);

        //  id form submitted
        if (!$form->isSubmitted()) {
            return new JsonResponse(['message' => 'Please submit form'], Response::HTTP_CONFLICT);
        }

        //  is form valid
        if (!$form->isValid()) {
            $errors = [];

            $all = $form->all();
            foreach ($all as $key => $value) {
                if (!$form->get($key)->isValid()) {
                    $errors[$key] = (string) $form->get($key)->getErrors();
                }
            }

            return new JsonResponse($errors, Response::HTTP_CONFLICT);
        }

        //  check CAPTCHA
        $captchaResponse = $request->get('network_feedback')['g-recaptcha-response'];
        $verified = $customService->verifyCaptcha($captchaResponse);
        if (!$verified) {
            return new JsonResponse(['message' => 'Captcha verification failed'], Response::HTTP_CONFLICT);
        }

        $em->persist($feedback);
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}