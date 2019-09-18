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
 * Class SearchApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/search")
 */
class SearchApiController extends Controller
{
    /**
     * @Route("", methods={"GET"})
     * @SWG\Get(
     *     summary="Default data for search (Publication / Author)",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Search")
     * @return Response
     */
    public function default()
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  GET POPULAR PUBLICATIONS
        $publications = $em->getRepository(Publication::class)->getPopularPublications();
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

        //  SEARCH IN AUTHORS
        $authors = $em->getRepository(Account::class)->getPopularAuthors(5, $account);
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

        return new JsonResponse(['publication' => $publications, 'authors' => $authors]);
    }

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
        $publications = $em->getRepository(Publication::class)->fulltextSearch($word, 5);
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
        $articles = $em->getRepository(ContentUnit::class)->fulltextSearch($word, 5);
        if ($articles) {
            try {
                $articles = $contentUnitService->prepare($articles);
            } catch (Exception $e) {
                return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
            }
        }
        $articles = $this->get('serializer')->normalize($articles, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
        $articles = $contentUnitService->prepareTags($articles);

        //  SEARCH IN AUTHORS
        $authors = $em->getRepository(Account::class)->fulltextSearch($word, 5);
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

    /**
     * @Route("/publication/{word}/{count}/{fromSlug}", methods={"POST"})
     * @SWG\Post(
     *     summary="Search for Publication",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=false, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Search")
     * @param string $word
     * @param int $count
     * @param string $fromSlug
     * @return Response
     */
    public function searchPublication(string $word, int $count, string $fromSlug)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Publication $publication
         */
        $publication = $em->getRepository(Publication::class)->findOneBy(['slug' => $fromSlug]);

        //  SEARCH IN PUBLICATIONS
        $publications = $em->getRepository(Publication::class)->fulltextSearch($word, $count + 1, $publication);
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

        return new JsonResponse(['publication' => $publications]);
    }
}