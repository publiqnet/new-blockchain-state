<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/28/19
 * Time: 12:05 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\ContentUnit;
use App\Entity\Publication;
use App\Entity\PublicationMember;
use App\Entity\Subscription;
use App\Service\ContentUnit as CUService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SearchController
 * @package AppBundle\Controller
 *
 * @Route("/api/search")
 */
class SearchController extends Controller
{
    /**
     * @Route("/{word}", methods={"POST"})
     * @SWG\Post(
     *     summary="Search for Publication / Article / Author",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Search")
     * @param string $word
     * @param CUService $contentUnitService
     * @return Response
     */
    public function search(string $word, CUService $contentUnitService)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  SEARCH IN PUBLICATIONS
        $publications = $em->getRepository(Publication::class)->fulltextSearch($word);
        if ($account && $publications) {
            /**
             * @var Publication $publication
             */
            foreach ($publications as $publication) {
                $memberStatus = 0;
                $publicationMember = $em->getRepository(PublicationMember::class)->findOneBy(['member' => $account, 'publication' => $publication]);

                //  if User is a Publication member return Publication info with members
                if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                    $memberStatus = $publicationMember->getStatus();
                }
                $publication->setMemberStatus($memberStatus);

                $subscription = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'publication' => $publication]);
                if ($subscription) {
                    $publication->setSubscribed(true);
                } else {
                    $publication->setSubscribed(false);
                }
            }
        }
        $publications = $this->get('serializer')->normalize($publications, null, ['groups' => ['publication', 'tag', 'publicationMemberStatus', 'publicationSubscribed']]);

        //  SEARCH IN ARTICLES
        $articles = $em->getRepository(ContentUnit::class)->fulltextSearch($word);
        if ($articles) {
            try {
                $articles = $contentUnitService->prepare($articles);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }
        $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['contentUnitSearch', 'tag', 'accountBase', 'publication']]);

        //  SEARCH IN AUTHORS
        $authors = $em->getRepository(Account::class)->fulltextSearch($word);
        if ($account && $authors) {
            /**
             * @var Account $author
             */
            foreach ($authors as $author) {
                //  check if user subscribed to author
                $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);
                if ($subscribed) {
                    $author->setSubscribed(true);
                } else {
                    $author->setSubscribed(false);
                }
            }
        }
        $authors = $this->get('serializer')->normalize($authors, null, ['groups' => ['accountBase', 'accountSubscribed']]);

        return new JsonResponse(['publication' => $publications, 'article' => $articles, 'authors' => $authors]);
    }
}