<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/25/18
 * Time: 12:23 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Subscription;
use App\Service\Oauth;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @return JsonResponse
     * @throws \Exception
     */
    public function authenticateUser(Request $request, Oauth $oauth)
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
            $account = $em->getRepository(Account::class)->findOneBy(['address' => $publicKey]);
            if (!$account) {
                $account = new Account();

                $account->setAddress($publicKey);
                $account->setEmail($email);
                $account->setWhole(0);
                $account->setFraction(0);
            } elseif (!$account->getEmail()) {
                $account->setEmail($email);
            }

            $account->setApiKey();

            $em->persist($account);
            $em->flush();

            $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);

            $account['publicKey'] = $account['address'];
            unset($account['address']);

            $account['token'] = $account['apiKey'];
            unset($account['apiKey']);

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
     */
    public function getUserData()
    {
        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);
        $account['publicKey'] = $account['address'];
        unset($account['address']);
        unset($account['apiKey']);

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
     *     @SWG\Parameter(name="image", in="formData", type="file", description="Image"),
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAccount(Request $request)
    {
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
        } else {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
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

            /**
             * @var UploadedFile $image
             */
            $image = $request->files->get('image');
            if ($image instanceof UploadedFile) {
                $account->setImage($moveFile($image, $currentAccountPath));
            }

            $em->persist($account);
            $em->flush();

            $account = $this->get('serializer')->normalize($account, null, ['groups' => ['account']]);
            $account['publicKey'] = $account['address'];
            unset($account['address']);
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
            $account['publicKey'] = $account['address'];
            unset($account['address']);
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
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        //  check if author exist
        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['address' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $stats = [
            'subscribersCount' => 0,
            'rating' => 0,
            'views' => 0,
            'articlesCount' => 0,
            'isSubscribed' => 0,
            'publicKey' => $publicKey,
            'firstName' => $author->getFirstName(),
            'lastName' => $author->getLastName(),
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

        //  replace address field with publicKey
        for ($i=0; $i<count($users); $i++) {
            $users[$i]['publicKey'] = $users[$i]['address'];
            unset($users[$i]['address']);
        }

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
     */
    public function getSubscriptions()
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        $subscriptions = $em->getRepository(Subscription::class)->findBy(['subscriber' => $account]);
        $subscriptions = $this->get('serializer')->normalize($subscriptions, null, ['groups' => ['subscription', 'publication', 'accountBase']]);

        return new JsonResponse($subscriptions);
    }

    /**
     * @Route("/{publicKey}/subscribe", methods={"POST"})
     * @SWG\Post(
     *     summary="Subscribe to Author",
     *     consumes={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=404, description="Publication not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="User")
     * @param string $publicKey
     * @return JsonResponse
     */
    public function subscribe(string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['address' => $publicKey]);
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
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
     * @param string $publicKey
     * @return JsonResponse
     */
    public function unsubscribe(string $publicKey)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $this->getUser();

        /**
         * @var Account $author
         */
        $author = $em->getRepository(Account::class)->findOneBy(['address' => $publicKey]);
        if (!$author) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        //  check if user is already subscribed
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['author' => $author, 'subscriber' => $account]);
        if ($subscription) {
            $em->remove($subscription);
            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}