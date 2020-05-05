<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/23/19
 * Time: 3:45 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Service\Custom;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FeeApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/fee")
 */
class FeeApiController extends AbstractController
{
    /**
     * @Route("", methods={"GET"})
     * @SWG\Get(
     *     summary="Get fee",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", required=true, in="header", type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Fee")
     * @param Custom $customService
     * @return Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function getFee(Custom $customService)
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        list($feeWhole, $feeFraction) = $customService->getFee();

        $firstArticle = false;
        $contentUnits = $account->getAuthorContentUnits();
        if (count($contentUnits) == 0) {
            $feeWhole = 0;
            $feeFraction = 0;
            $firstArticle = true;
        }

        $date = new \DateTime();
        $timezone = new \DateTimeZone('UTC');
        $date->setTimezone($timezone);

        return new JsonResponse(['whole' => $feeWhole, 'fraction' => $feeFraction, 'firstArticle' => $firstArticle, 'currentTime' => $date->getTimestamp()]);
    }
}