<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/25/18
 * Time: 12:23 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\AccountExchange;
use App\Entity\ContentUnit;
use App\Entity\Draft;
use App\Entity\Publication;
use App\Entity\Subscription;
use App\Event\SubscribeUserEvent;
use App\Event\UnsubscribeUserEvent;
use App\Service\Oauth;
use App\Service\Custom;
use App\Service\ContentUnit as CUService;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

/**
 * Class AccountApiController
 * @package App\Controller
 *
 * @Route("/api/user")
 */
class AccountApiController extends AbstractController
{
    /**
     * @Route("/authenticate", methods={"GET"})
     * @SWG\Get(
     *     summary="Get user by oAuth token",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-OAUTH-TOKEN", in="header", required=true, type="string")
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Tag(name="User")
     * @param Request $request
     * @param Oauth $oauth
     * @param Custom $customService
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function authenticateUser(Request $request, Oauth $oauth, Custom $customService)
    {
        $em = $this->getDoctrine()->getManager();

        $token = $request->headers->get('X-OAUTH-TOKEN');
        if (!$token) {
            return new JsonResponse(['message' => 'Empty token'], Response::HTTP_CONFLICT);
        }

        try {
            $checkResponse = $oauth->authenticateUserByToken($token);

            if ($checkResponse['status'] == 404) {
                return new JsonResponse('', Response::HTTP_NOT_FOUND);
            }

            $publicKey = $checkResponse['data']['publicKey'];
            $email = $checkResponse['data']['email'];

            //  check if account exist
            /**
             * @var Account $account
             */
            $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
            if (!$account) {
                $account = new Account();

                $account->setPublicKey($publicKey);
                $account->setEmail($email);
                $account->setWhole(0);
                $account->setFraction(0);
            } elseif (!$account->getEmail()) {
                $account->setEmail($email);
            }

            if ($this->getParameter('environment') == 'prod' || !$account->getApiKey()) {
                $account->setApiKey();
            }

            $em->persist($account);
            $em->flush();

//            if (!$account->getOldPublicKey()) {
//                $oldPublicKey = $customService->getOldPublicKey($email);
//                if ($oldPublicKey) {
//                    $account->setOldPublicKey($oldPublicKey);
//                    $em->persist($account);
//                    $em->flush();
//
//                    /**
//                     * @var Draft[] $drafts
//                     */
//                    $drafts = $em->getRepository(Draft::class)->findBy(['publicKey' => $oldPublicKey]);
//                    if ($drafts) {
//                        foreach ($drafts as $draft) {
//                            $draft->setAccount($account);
//                            $em->persist($draft);
//                        }
//                        $em->flush();
//                    }
//                }
//            }

            $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);

            $account['token'] = $account['apiKey'];
            unset($account['apiKey']);

            //  create jwt token
            $topic = $this->getParameter('mercure_topic');
            $token = (new Builder())
                ->set('mercure', ['subscribe' => [$topic . "/user/" . $account['publicKey']]])
                ->sign(new Sha256(), $this->getParameter('mercure_secret_key'))
                ->getToken();
            $account['jwtToken'] = (string) $token;

            return new JsonResponse($account);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("", methods={"GET"})
     * @SWG\Get(
     *     summary="Get user by local token",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="User")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getUserData()
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);
        unset($account['apiKey']);

        //  create jwt token
        $topic = $this->getParameter('mercure_topic');
        $token = (new Builder())
            ->set('mercure', ['subscribe' => [$topic . "/user/" . $account['publicKey']]])
            ->sign(new Sha256(), $this->getParameter('mercure_secret_key'))
            ->getToken();
        $account['jwtToken'] = (string) $token;

        return new JsonResponse($account);
    }

    /**
     * @Route("/signout", methods={"POST"}, name="logout_account")
     * @SWG\Post(
     *     summary="Logout user",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @return JsonResponse
     * @throws \Exception
     */
    public function signout()
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        try {
            $account->setApiKey();
            $em->persist($account);
            $em->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("", methods={"POST"}, name="update_account")
     * @SWG\Post(
     *     summary="Update user data",
     *     consumes={"multipart/form-data"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="firstName", in="formData", type="string", description="First name"),
     *     @SWG\Parameter(name="lastName", in="formData", type="string", description="Last name"),
     *     @SWG\Parameter(name="bio", in="formData", type="string", description="Biography"),
     *     @SWG\Parameter(name="listView", in="formData", type="boolean", description="List view"),
     *     @SWG\Parameter(name="deleteImage", in="formData", type="boolean", description="Delete image"),
     *     @SWG\Parameter(name="image", in="formData", type="file", description="Image"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param Request $request
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function updateAccount(Request $request)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $content = json_decode($content, true);

            $firstName = $content['firstName'];
            $lastName = $content['lastName'];
            $bio = $content['bio'];
            $listView = $content['listView'];
            $deleteImage = $content['deleteImage'];
        } else {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $bio = $request->request->get('bio');
            $listView = $request->request->get('listView');
            $deleteImage = $request->request->get('deleteImage');
        }

        //  get upload path from configs
        $accountsPath = $this->getParameter('accounts_path');

        //  create folder for publication
        $currentAccountPath = $accountsPath . '/' . $account->getId();
        if (!file_exists($currentAccountPath)) {
            mkdir($currentAccountPath);
        }

        //  local function to move uploaded files
        $moveFile = function (UploadedFile $file, string $path) {
            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
            $file->move($path, $fileName);
            return $path . '/' . $fileName;
        };

        //  save data into database
        try {
            $account->setFirstName($firstName);
            $account->setLastName($lastName);
            $account->setBio($bio);
            $account->setListView($listView);

            /**
             * @var UploadedFile $image
             */
            $image = $request->files->get('image');
            if ($image instanceof UploadedFile) {
                $account->setImage($moveFile($image, $currentAccountPath));

                //  set author articles social images as 'must be updated'
                /**
                 * @var ContentUnit[] $contentUnits
                 */
                $contentUnits = $em->getRepository(ContentUnit::class)->getAuthorArticles($account, 9999);
                if ($contentUnits) {
                    foreach ($contentUnits as $contentUnit) {
                        $contentUnit->setUpdateSocialImage(true);
                        $em->persist($contentUnit);
                    }

                    $em->flush();
                }
            } elseif ($deleteImage) {
                $account->setImage(null);
                $account->setThumbnail(null);

                //  set author articles social images as 'must be updated'
                /**
                 * @var ContentUnit[] $contentUnits
                 */
                $contentUnits = $em->getRepository(ContentUnit::class)->getAuthorArticles($account, 9999);
                if ($contentUnits) {
                    foreach ($contentUnits as $contentUnit) {
                        $contentUnit->setUpdateSocialImage(true);
                        $em->persist($contentUnit);
                    }

                    $em->flush();
                }
            }

            $em->persist($account);
            $em->flush();

            $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);
            unset($account['apiKey']);

            return new JsonResponse($account);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/set-language/{language}", methods={"POST"}, name="update_account_language")
     * @SWG\Post(
     *     summary="Update user language",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param string $language
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function updateLanguage(string $language)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  save data into database
        try {
            $account->setLanguage($language);
            $em->persist($account);
            $em->flush();

            $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);
            unset($account['apiKey']);

            return new JsonResponse($account);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

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
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  check if author exist
        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user subscribed to author
        $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);

        //  get author total subscribers
        $subscribers = $em->getRepository(Subscription::class)->findBy(['author' => $author]);

        //  get author articles count
        $articles = $em->getRepository(ContentUnit::class)->getAuthorArticlesCount($author);

        //  calculate total views
        $views = $em->getRepository(ContentUnit::class)->getAuthorArticlesViews($author);

        $stats = [
            'subscribersCount' => count($subscribers),
            'rating' => 0,
            'views' => intval($views[0][1]),
            'articlesCount' => count($articles),
            'subscribed' => ($subscribed ? 1: 0),
            'publicKey' => $publicKey,
            'firstName' => $author->getFirstName(),
            'lastName' => $author->getLastName(),
            'bio' => $author->getBio(),
            'listView' => $author->getListView(),
            'image' => $author->getImage(),
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
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function searchUsers($searchWord)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $users = $em->getRepository(Account::class)->searchUsers($searchWord, $account);
        $users = $this->get('serializer')->normalize($users, null, ['groups' => ['accountBase']]);

        return new JsonResponse($users);
    }

    /**
     * @Route("/subscriptions", methods={"GET"}, name="get_user_subscriptions")
     * @SWG\Get(
     *     summary="Get user subscriptions",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", type="string")
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Tag(name="User")
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getSubscriptions()
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();
        if (!$account) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $subscriptionsAuthors = $em->getRepository(Account::class)->getUserSubscriptions($account);
        $subscriptionsPublications = $em->getRepository(Publication::class)->getUserSubscriptions($account);

        $subscriptionsAuthors = $this->get('serializer')->normalize($subscriptionsAuthors, null, ['groups' => ['accountBase']]);
        $subscriptionsPublications = $this->get('serializer')->normalize($subscriptionsPublications, null, ['groups' => ['publication']]);

        return new JsonResponse(['authors' => $subscriptionsAuthors, 'publications' => $subscriptionsPublications]);
    }

    /**
     * @Route("/{publicKey}/subscribe", methods={"POST"})
     * @SWG\Post(
     *     summary="Subscribe to Author",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Author not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param EventDispatcherInterface $eventDispatcher
     * @param string $publicKey
     * @return JsonResponse
     */
    public function subscribe(EventDispatcherInterface $eventDispatcher, string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is already subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['author' => $author, 'subscriber' => $account]);
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setAuthor($author);
            $subscription->setSubscriber($account);

            $em->persist($subscription);
            $em->flush();

            // notify author
            $eventDispatcher->dispatch(
                new SubscribeUserEvent($account, $author),
                SubscribeUserEvent::NAME
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{publicKey}/subscribers/{count}/{from}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get Author subscribers",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Author not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param string $publicKey
     * @param int $count
     * @param int $from
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function subscribers(string $publicKey, int $count, int $from)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['author' => $author, 'subscriber' => $account]);
        if ($subscription || $account === $author) {
            /**
             * @var Account[] $subscribers
             */
            $subscribers = $em->getRepository(Account::class)->getAuthorSubscribers($author, ($count + 1), $from);
            if ($subscribers) {
                foreach ($subscribers as $subscriber) {
                    //  get subscribers
                    $subscribersCount = $em->getRepository(Account::class)->getAuthorSubscribersCount($subscriber);
                    $subscriber->setSubscribersCount($subscribersCount[0]['totalCount']);

                    //  check if user subscribed to author
                    $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $subscriber]);
                    if ($subscribed) {
                        $subscriber->setSubscribed(true);
                    } else {
                        $subscriber->setSubscribed(false);
                    }
                }
            }
            $subscribers = $this->get('serializer')->normalize($subscribers, null, ['groups' => ['accountBase', 'accountSubscribed']]);

            $more = false;
            if (count($subscribers) > $count) {
                unset($subscribers[$count]);
                $more = true;
            }

            return new JsonResponse(['subscribers' => $subscribers, 'more' => $more]);
        }

        return new JsonResponse(null, Response::HTTP_FORBIDDEN);
    }

    /**
     * @Route("/{publicKey}/subscribe", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Unsubscribe from Author",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param EventDispatcherInterface $eventDispatcher
     * @param string $publicKey
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function unsubscribe(EventDispatcherInterface $eventDispatcher, string $publicKey)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is already subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['author' => $author, 'subscriber' => $account]);
        if ($subscription) {
            $em->remove($subscription);
            $em->flush();

            // notify author
            $eventDispatcher->dispatch(
                new UnsubscribeUserEvent($account, $author),
                UnsubscribeUserEvent::NAME
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/homepage-data", methods={"GET"}, name="get_homepage_data")
     * @SWG\Get(
     *     summary="Get user homepage data",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Parameter(name="X-API-TOKEN", in="header", type="string", required=false)
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Tag(name="User")
     * @param CUService $contentUnitService
     * @param Custom $customService
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws Exception
     */
    public function getHomepageData(CUService $contentUnitService, Custom $customService)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        $preferredAuthorsArticles = null;
        $preferredTagsArticles = null;
        $firstArticle = null;
        $nonBoostedArticle = null;
        $recommendedPublications = null;
        $recommendedAuthors = null;
        $fee = null;

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        if ($account) {
            //  IF USER HAS ARTICLE
            $firstArticle = false;
            $contentUnits = $account->getAuthorContentUnits();
            if (count($contentUnits) == 0) {
                $firstArticle = true;
            }

            //  PREFERENCES - articles by author
            $preferredAuthorsArticles = $em->getRepository(ContentUnit::class)->getUserPreferredAuthorsArticles($account, 4);
            if ($preferredAuthorsArticles) {
                try {
                    $preferredAuthorsArticles = $contentUnitService->prepare($preferredAuthorsArticles);
                } catch (Exception $e) {
                    return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
                }
            }
            $preferredAuthorsArticles = $this->get('serializer')->normalize($preferredAuthorsArticles, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
            $preferredAuthorsArticles = $contentUnitService->prepareTags($preferredAuthorsArticles);

            //  PREFERENCES - articles by tags
            $preferredTagsArticles = $em->getRepository(ContentUnit::class)->getUserPreferredTagsArticles($account, 4);
            if ($preferredTagsArticles) {
                try {
                    $preferredTagsArticles = $contentUnitService->prepare($preferredTagsArticles);
                } catch (Exception $e) {
                    return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
                }
            }
            $preferredTagsArticles = $this->get('serializer')->normalize($preferredTagsArticles, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
            $preferredTagsArticles = $contentUnitService->prepareTags($preferredTagsArticles);

            //  RECOMMENDATIONS - publications
            /**
             * @var Publication[] $recommendedPublications
             */
            $recommendedPublications = $em->getRepository(Publication::class)->getUserRecommendedPublications($account,16);
            if ($recommendedPublications) {
                foreach ($recommendedPublications as $publication) {
                    //  get subscribers
                    $subscribersCount = $em->getRepository(Account::class)->getPublicationSubscribersCount($publication);
                    $publication->setSubscribersCount($subscribersCount[0]['totalCount']);

                    $publication->setMembersCount(count($publication->getMembers()));

                    //  check if user subscribed to Publication
                    $subscription = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'publication' => $publication]);
                    if ($subscription) {
                        $publication->setSubscribed(true);
                    } else {
                        $publication->setSubscribed(false);
                    }
                }
            }
            $recommendedPublications = $this->get('serializer')->normalize($recommendedPublications, null, ['groups' => ['trending', 'publicationSubscribed']]);

            //  RECOMMENDATIONS - authors
            /**
             * @var Account[] $recommendedAuthors
             */
            $recommendedAuthors = $em->getRepository(Account::class)->getUserRecommendedAuthors($account,16);
            if ($recommendedAuthors) {
                foreach ($recommendedAuthors as $author) {
                    //  get subscribers
                    $subscribersCount = $em->getRepository(Account::class)->getAuthorSubscribersCount($author);
                    $author->setSubscribersCount($subscribersCount[0]['totalCount']);

                    //  check if user subscribed to author
                    $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);
                    if ($subscribed) {
                        $author->setSubscribed(true);
                    } else {
                        $author->setSubscribed(false);
                    }
                }
            }
            $recommendedAuthors = $this->get('serializer')->normalize($recommendedAuthors, null, ['groups' => ['accountBase', 'accountSubscribed']]);

            //  GET AUTHOR NOT ACTIVE BOOSTED ARTICLE
            $nonBoostedArticle = $em->getRepository(ContentUnit::class)->getAuthorNonBoostedRandomArticle($account);
            if ($nonBoostedArticle) {
                $nonBoostedArticle = $contentUnitService->prepare([$nonBoostedArticle]);
                $nonBoostedArticle = $this->get('serializer')->normalize($nonBoostedArticle, null, ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
                $nonBoostedArticle = $contentUnitService->prepareTags($nonBoostedArticle);
                $nonBoostedArticle = $nonBoostedArticle[0];
            }

            //  GET FEE
            list($feeWhole, $feeFraction) = $customService->getFee();

            $date = new \DateTime();
            $timezone = new \DateTimeZone('UTC');
            $date->setTimezone($timezone);

            $fee = ['whole' => $feeWhole, 'fraction' => $feeFraction, 'currentTime' => $date->getTimestamp()];
        }


        //  TRENDING  - publications
        /**
         * @var Publication[] $trendingPublications
         */
        $trendingPublications = $em->getRepository(Publication::class)->getCurrentTrendingPublications();
        if ($trendingPublications) {
            foreach ($trendingPublications as $publication) {
                //  get subscribers
                $subscribersCount = $em->getRepository(Account::class)->getPublicationSubscribersCount($publication);
                $publication->setSubscribersCount($subscribersCount[0]['totalCount']);

                $publication->setMembersCount(count($publication->getMembers()));

                //  check if user subscribed to Publication
                $subscription = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'publication' => $publication]);
                if ($subscription) {
                    $publication->setSubscribed(true);
                } else {
                    $publication->setSubscribed(false);
                }
            }
        }
        $trendingPublications = $this->get('serializer')->normalize($trendingPublications, null, ['groups' => ['trending', 'publicationSubscribed']]);

        //  TRENDING  - authors
        /**
         * @var Account[] $trendingAuthors
         */
        $trendingAuthors = $em->getRepository(Account::class)->getCurrentTrendingAuthors();
        if ($trendingAuthors) {
            foreach ($trendingAuthors as $author) {
                //  get subscribers
                $subscribersCount = $em->getRepository(Account::class)->getAuthorSubscribersCount($author);
                $author->setSubscribersCount($subscribersCount[0]['totalCount']);

                //  check if user subscribed to author
                $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);
                if ($subscribed) {
                    $author->setSubscribed(true);
                } else {
                    $author->setSubscribed(false);
                }
            }
        }
        $trendingAuthors = $this->get('serializer')->normalize($trendingAuthors, null, ['groups' => ['accountBase', 'accountSubscribed']]);

        //  HIGHLIGHTS
        $highlights = $em->getRepository(ContentUnit::class)->getHighlights(20);
        if ($highlights) {
            $highlights = $contentUnitService->prepare($highlights, true);
        }
        $highlights = $this->get('serializer')->normalize($highlights, null, ['groups' => ['contentUnitList', 'highlight', 'tag', 'file', 'accountBase', 'publication']]);
        $highlights = $contentUnitService->prepareTags($highlights);

        return new JsonResponse([
            'preferences' => ['author' => $preferredAuthorsArticles, 'tag' => $preferredTagsArticles],
            'firstArticle' => $firstArticle,
            'articleToBoost' => $nonBoostedArticle,
            'currentBoostFee' => $fee,
            'trending' => ['publications' => $trendingPublications, 'authors' => $trendingAuthors],
            'recommended' => ['publications' => $recommendedPublications, 'authors' => $recommendedAuthors],
            'highlights' => $highlights
        ]);
    }

    /**
     * @Route("/subscriptions/check", methods={"POST"})
     * @SWG\Post(
     *     summary="Check subscription",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="publicKeys", type="array", items={"type": "string"}),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param Request $request
     * @return Response
     */
    public function checkSubscription(Request $request)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $contentArr = json_decode($content, true);

            $publicKeys = $contentArr['publicKeys'];
        } else {
            $publicKeys = $request->request->get('publicKeys');
        }

        try {
            $subscriptionStatus = [];
            foreach ($publicKeys as $publicKey) {
                //  check if user subscribed to author
                $author = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
                $subscribed = $em->getRepository(Subscription::class)->findOneBy(['subscriber' => $account, 'author' => $author]);

                $subscriptionStatus[$publicKey] = $subscribed ? true: false;
            }

            return new JsonResponse($subscriptionStatus);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/exchange/add", methods={"POST"})
     * @SWG\Post(
     *     summary="Add exchange",
     *     consumes={"application/json"},
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         description="JSON Payload",
     *         required=true,
     *         format="application/json",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="exchangeId", type="string"),
     *         )
     *     ),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param Request $request
     * @return Response
     */
    public function addExchange(Request $request)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  get data from submitted data
        $contentType = $request->getContentType();
        if ($contentType == 'application/json' || $contentType == 'json') {
            $content = $request->getContent();
            $contentArr = json_decode($content, true);

            $exchangeId = $contentArr['exchangeId'];
        } else {
            $exchangeId = $request->request->get('exchangeId');
        }

        try {
            $accountExchange = $em->getRepository(AccountExchange::class)->findOneBy(['exchangeId' => $exchangeId]);
            if ($accountExchange) {
                return new JsonResponse(['message' => 'exchange_id_exist'], Response::HTTP_CONFLICT);
            }

            $accountExchange = new AccountExchange();
            $accountExchange->setAccount($account);
            $accountExchange->setExchangeId($exchangeId);
            $em->persist($accountExchange);
            $em->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }
}