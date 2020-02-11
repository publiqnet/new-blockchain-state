<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/10/19
 * Time: 5:03 PM
 */

namespace App\Service;

use App\Entity\NotificationType;
use App\Entity\Publication;
use App\Entity\ContentUnit;
use App\Entity\PublicationMember;
use App\Entity\UserNotification as UN;
use App\Entity\Notification;
use App\Entity\Account;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Symfony\Component\Mercure\Jwt\StaticJwtProvider;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\Serializer;

class UserNotification
{
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var Serializer $serializer
     */
    private $serializer;
    private $mercureHub;
    private $mercureSecretKey;
    private $excludeChannelsAddresses;

    public function __construct(EntityManagerInterface $em, Serializer $serializer, string $mercureHub, string $mercureSecretKey, string $excludeChannelsAddresses)
    {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->mercureHub = $mercureHub;
        $this->mercureSecretKey = $mercureSecretKey;
        $this->excludeChannelsAddresses = $excludeChannelsAddresses;
    }

    /**
     * @param string $notificationKey
     * @param Account $performer
     * @param string $data
     * @param Publication|null $publication
     * @param ContentUnit|null $contentUnit
     * @return Notification
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createNotification(string $notificationKey, Account $performer = null, string $data = null, Publication $publication = null, ContentUnit $contentUnit = null): Notification
    {
        $notificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => $notificationKey]);

        $notification = new Notification();
        $notification->setNotificationType($notificationType);
        if ($performer) $notification->setPerformer($performer);
        if ($data) $notification->setData($data);
        if ($publication) $notification->setPublication($publication);
        if ($contentUnit) $notification->setContentUnit($contentUnit);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * @param Account $user
     * @param Notification $notification
     * @param bool $special
     * @return \App\Entity\UserNotification
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function notify(Account $user, Notification $notification, bool $special = false)
    {
        $userNotification = new UN();
        $userNotification->setAccount($user);
        $userNotification->setNotification($notification);
        $userNotification->setIsSpecial($special);

        $this->em->persist($userNotification);
        $this->em->flush();

        //  enable channel exclude filter
        $this->em->getFilters()->enable('channel_exclude_filter');
        $this->em->getFilters()->getFilter('channel_exclude_filter')->setParameter('exclude_channels_addresses', $this->excludeChannelsAddresses);

        //  MERCURE
        $token = (new Builder())
            ->set('mercure', ['publish' => ["http://publiq.site/user/" . $user->getPublicKey()]])
            ->sign(new Sha256(), $this->mercureSecretKey)
            ->getToken();

        $jwtProvider = new StaticJwtProvider($token);
        $publisher = new Publisher($this->mercureHub, $jwtProvider);


        //  get user last 10 notifications
        $unreadNotifications = $this->em->getRepository(UN::class)->getUserUnreadNotifications($user);
        $unseenNotifications = $this->em->getRepository(UN::class)->getUserUnseenNotifications($user);

        /**
         * @var Notification[] $notifications
         */
        $notifications = $this->em->getRepository(Notification::class)->getUserNotifications($user, 11);
        $notifications = $this->serializer->normalize($notifications, null, ['groups' => ['userNotification', 'notification', 'notificationType', 'publication', 'accountBase', 'contentUnitNotification']]);

        $notificationsRewrited = [];
        for ($i=0; $i<count($notifications); $i++) {
            $notificationSingle = $notifications[$i][0];

            unset($notifications[$i][0]);
            foreach ($notifications[$i] as $key => $notificationExtra) {
                $notificationSingle[$key] = $notificationExtra;
            }

            $notificationsRewrited[] = $notificationSingle;
        }

        $more = false;
        if (count($notificationsRewrited) > 10) {
            unset($notificationsRewrited[10]);
            $more = true;
        }

        $data = [];
        $data[] = ['type' => 'notification', 'data' => ['notifications' => $notificationsRewrited, 'more' => $more, 'unreadCount' => count($unreadNotifications), 'unseenCount' => count($unseenNotifications)]];

        //  check for special types
        $notificationType = $notification->getType();

        $shareArticleNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['share_article']['key']]);
        if ($notificationType == $shareArticleNotificationType) {
            $article = $notification->getContentUnit();
            $article = $this->serializer->normalize($article, null, ['groups' => ['contentUnitNotification']]);

            $data[] = ['type' => 'article_published', 'data' => $article];
        }

        $publicationInvitationNewNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_invitation_new']['key']]);
        if ($notificationType == $publicationInvitationNewNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_invitation_new', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationCancelledNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_invitation_cancelled']['key']]);
        if ($notificationType == $publicationInvitationCancelledNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_invitation_cancelled', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationAcceptedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_invitation_accepted']['key']]);
        if ($notificationType == $publicationInvitationAcceptedNotificationType) {
            $publication = $notification->getPublication();
            $performer = $notification->getPerformer();

            $publicationMember = $this->em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $performer]);
            if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                $memberStatus = $publicationMember->getStatus();
            } else {
                $memberStatus = 0;
            }

            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_invitation_accepted', 'data' => ['publication' => $publication, 'performer' => $performer, 'memberStatus' => $memberStatus]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_invitation_rejected']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_invitation_rejected', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_request_new']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_request_new', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_request_cancelled']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_request_cancelled', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_request_accepted']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $performer = $notification->getPerformer();

            $publicationMember = $this->em->getRepository(PublicationMember::class)->findOneBy(['publication' => $publication, 'member' => $user]);
            if ($publicationMember && in_array($publicationMember->getStatus(), [PublicationMember::TYPES['owner'], PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']])) {
                $memberStatus = $publicationMember->getStatus();
            } else {
                $memberStatus = 0;
            }

            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_request_accepted', 'data' => ['publication' => $publication, 'performer' => $performer, 'memberStatus' => $memberStatus]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_request_rejected']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_request_rejected', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_membership_cancelled']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_membership_cancelled', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $publicationInvitationRejectedNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['publication_membership_cancelled_by_user']['key']]);
        if ($notificationType == $publicationInvitationRejectedNotificationType) {
            $publication = $notification->getPublication();
            $publication = $this->serializer->normalize($publication, null, ['groups' => ['publicationBase']]);

            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'publication_membership_cancelled_by_user', 'data' => ['publication' => $publication, 'performer' => $performer]];
        }

        $subscriptionNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['subscribe_user']['key']]);
        if ($notificationType == $subscriptionNotificationType) {
            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'subscribe_user', 'data' => ['performer' => $performer]];
        }

        $unsubscriptionNotificationType = $this->em->getRepository(NotificationType::class)->findOneBy(['keyword' => NotificationType::TYPES['unsubscribe_user']['key']]);
        if ($notificationType == $unsubscriptionNotificationType) {
            $performer = $notification->getPerformer();
            $performer = $this->serializer->normalize($performer, null, ['groups' => ['accountBase']]);

            $data[] = ['type' => 'unsubscribe_user', 'data' => ['performer' => $performer]];
        }

        $update = new Update(
            'http://publiq.site/notification',
            json_encode($data),
            ["http://publiq.site/user/" . $user->getPublicKey()]
        );
        $publisher($update);

        return $userNotification;
    }
}