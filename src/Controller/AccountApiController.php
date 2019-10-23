<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/25/18
 * Time: 12:23 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\ContentUnit;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AccountApiController
 * @package App\Controller
 *
 * @Route("/api/user")
 */
class AccountApiController extends Controller
{
    /**
     * @Route("/author-data/{publicKey}", methods={"GET"}, name="get_author_data")
     * @SWG\Get(
     *     summary="Get author data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", type="string")
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Tag(name="User")
     * @param string $publicKey
     * @return JsonResponse
     */
    public function getAuthorStats(string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        //  check if author exist
        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  get author articles count
        $articles = $em->getRepository(ContentUnit::class)->getAuthorArticlesCount($author);

        //  calculate total views
        $views = $em->getRepository(ContentUnit::class)->getAuthorArticlesViews($author);

        $stats = [
            'views' => intval($views[0][1]),
            'articlesCount' => count($articles),
            'publicKey' => $publicKey,
        ];

        return new JsonResponse($stats);
    }

    /**
     * @Route("/search/{searchWord}", methods={"GET"}, name="search_users")
     * @SWG\Get(
     *     summary="Search in user",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", type="string")
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="User")
     * @param $searchWord
     * @return JsonResponse
     */
    public function searchUsers($searchWord)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $users = $em->getRepository(Account::class)->searchUsers($searchWord, $account);
        $users = $this->get('serializer')->normalize($users, null, ['groups' => ['accountBase']]);

        return new JsonResponse($users);
    }
}