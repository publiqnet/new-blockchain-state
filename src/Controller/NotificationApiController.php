<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 1:02 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Notification;
use App\Entity\UserNotification;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;

/**
 * @package App\Controller
 * @Route("/api/notification")
 */
class NotificationApiController extends AbstractController
{
    /**
     * @Route("/read-all", methods={"POST"})
     * @SWG\Post(
     *     summary="Mark all notifications as reed",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @return JsonResponse
     */
    public function markAllAsReed()
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

        $em->getRepository(UserNotification::class)->markAllAsRead($account);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/seen-all", methods={"POST"})
     * @SWG\Post(
     *     summary="Mark all notifications as seen",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @return JsonResponse
     */
    public function markAllAsSeen()
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

        $em->getRepository(UserNotification::class)->markAllAsSeen($account);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/read/{notificationId}", methods={"POST"})
     * @SWG\Post(
     *     summary="Mark notification as reed",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @param int $notificationId
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     */
    public function markAsReed(int $notificationId)
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

        /**
         * @var UserNotification $userNotification
         */
        $userNotification = $em->getRepository(UserNotification::class)->find($notificationId);

        if (!$userNotification || $userNotification->getAccount() !== $account) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $userNotification->setIsRead(true);
        $em->persist($userNotification);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/unread/{notificationId}", methods={"POST"})
     * @SWG\Post(
     *     summary="Mark notification as unread",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @param int $notificationId
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     */
    public function markAsUnread(int $notificationId)
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

        /**
         * @var UserNotification $userNotification
         */
        $userNotification = $em->getRepository(UserNotification::class)->find($notificationId);

        if (!$userNotification || $userNotification->getAccount() !== $account) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $userNotification->setIsRead(false);
        $em->persist($userNotification);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/delete/{notificationId}", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete notification",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @param int $notificationId
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     */
    public function delete(int $notificationId)
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

        /**
         * @var UserNotification $userNotification
         */
        $userNotification = $em->getRepository(UserNotification::class)->find($notificationId);

        if (!$userNotification || $userNotification->getAccount() !== $account) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $em->remove($userNotification);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/delete-all", methods={"DELETE"})
     * @SWG\Delete(
     *     summary="Delete all user notifications",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=404, description="Not found")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @return JsonResponse
     * @throws \Doctrine\ORM\ORMException
     */
    public function deleteAll()
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

        /**
         * @var UserNotification $userNotifications[]
         */
        $userNotifications = $em->getRepository(UserNotification::class)->findBy(['account' => $account]);

        if ($userNotifications) {
            foreach ($userNotifications as $userNotification) {
                $em->remove($userNotification);
            }

            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{count}/{fromId}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get notifications",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="X-API-TOKEN", in="header", required=true, type="string")
     * )
     * @SWG\Response(response=204, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Response(response=409, description="Error - see description for more information")
     * @SWG\Tag(name="Notification")
     * @param int $count
     * @param int $fromId
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAll(int $count, int $fromId)
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

        $unreadNotifications = $em->getRepository(UserNotification::class)->getUserUnreadNotifications($account);
        $unseenNotifications = $em->getRepository(UserNotification::class)->getUserUnseenNotifications($account);

        /**
         * @var Notification[] $notifications
         */
        $notifications = $em->getRepository(Notification::class)->getUserNotifications($account, $count + 1, $fromId);
        $notifications = $this->get('serializer')->normalize($notifications, null, ['groups' => ['userNotification', 'notification', 'notificationType', 'publication', 'accountBase', 'contentUnitNotification']]);

        $notificationsRewrited = [];
        for ($i=0; $i<count($notifications); $i++) {
            $notification = $notifications[$i][0];

            unset($notifications[$i][0]);
            foreach ($notifications[$i] as $key => $notificationExtra) {
                $notification[$key] = $notificationExtra;
            }

            $notificationsRewrited[] = $notification;
        }

        $more = false;
        if (count($notificationsRewrited) > $count) {
            unset($notificationsRewrited[$count]);
            $more = true;
        }

        return new JsonResponse(['notifications' => $notificationsRewrited, 'more' => $more, 'unreadCount' => count($unreadNotifications), 'unseenCount' => count($unseenNotifications)]);
    }
}