<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/10/19
 * Time: 4:55 PM
 */

namespace App\EventSubscriber;

use App\Entity\Account;
use App\Entity\NotificationType;
use App\Event\PublicationInvitationAcceptEvent;
use App\Event\PublicationInvitationCancelEvent;
use App\Event\PublicationInvitationRejectEvent;
use App\Event\PublicationInvitationRequestEvent;
use App\Event\PublicationMembershipCancelEvent;
use App\Event\PublicationMembershipRequestAcceptEvent;
use App\Event\PublicationMembershipRequestCancelEvent;
use App\Event\PublicationMembershipRequestEvent;
use App\Event\PublicationMembershipRequestRejectEvent;
use App\Service\UserNotification;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GeneralEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManager
     */
    public $em;

    /**
     * @var UserNotification
     */
    private $userNotificationService;

    public function __construct(EntityManagerInterface $em, UserNotification $userNotificationService)
    {
        $this->em = $em;
        $this->userNotificationService = $userNotificationService;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            PublicationInvitationRequestEvent::NAME => 'onPublicationInvitationRequest',
            PublicationInvitationCancelEvent::NAME => 'onPublicationInvitationCancel',
            PublicationInvitationAcceptEvent::NAME => 'onPublicationInvitationAccept',
            PublicationInvitationRejectEvent::NAME => 'onPublicationInvitationReject',
            PublicationMembershipRequestEvent::NAME => 'onPublicationMembershipRequest',
            PublicationMembershipRequestCancelEvent::NAME => 'onPublicationMembershipRequestCancel',
            PublicationMembershipRequestAcceptEvent::NAME => 'onPublicationMembershipRequestAcceptEvent',
            PublicationMembershipRequestRejectEvent::NAME => 'onPublicationMembershipRequestRejectEvent',
            PublicationMembershipCancelEvent::NAME => 'onPublicationMembershipCancelEvent',
        ];
    }

    /**
     * Called whenever a new request is made
     * @param GetResponseEvent $event
     * @return null
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        return null;
    }

    /**
     * @param PublicationInvitationRequestEvent $event
     */
    public function onPublicationInvitationRequest(PublicationInvitationRequestEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_invitation_new']['key'], $performer, ($user->getPublicKey() ? $user->getPublicKey() : $user->getEmail()), $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationInvitationCancelEvent $event
     */
    public function onPublicationInvitationCancel(PublicationInvitationCancelEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_invitation_cancelled']['key'], $performer, ($user->getPublicKey() ? $user->getPublicKey() : $user->getEmail()), $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationInvitationAcceptEvent $event
     */
    public function onPublicationInvitationAccept(PublicationInvitationAcceptEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_invitation_accepted']['key'], $performer, 'Invitation accepted', $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationInvitationRejectEvent $event
     */
    public function onPublicationInvitationReject(PublicationInvitationRejectEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_invitation_rejected']['key'], $performer, 'Invitation rejected', $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationMembershipRequestEvent $event
     */
    public function onPublicationMembershipRequest(PublicationMembershipRequestEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_request_new']['key'], $performer, 'New request', $publication);

            //  OWNER
            $publicationOwner = $this->em->getRepository(Account::class)->getPublicationOwner($publication);
            $this->userNotificationService->notify($publicationOwner, $notification);

            //  EDITORS
            $publicationEditors = $this->em->getRepository(Account::class)->getPublicationEditors($publication);
            if ($publicationEditors) {
                foreach ($publicationEditors as $publicationEditor) {
                    $this->userNotificationService->notify($publicationEditor, $notification);
                }
            }
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationMembershipRequestCancelEvent $event
     */
    public function onPublicationMembershipRequestCancel(PublicationMembershipRequestCancelEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_request_cancelled']['key'], $performer, 'Request cancelled', $publication);

            //  OWNER
            $publicationOwner = $this->em->getRepository(Account::class)->getPublicationOwner($publication);
            $this->userNotificationService->notify($publicationOwner, $notification);

            //  EDITORS
            $publicationEditors = $this->em->getRepository(Account::class)->getPublicationEditors($publication);
            if ($publicationEditors) {
                foreach ($publicationEditors as $publicationEditor) {
                    $this->userNotificationService->notify($publicationEditor, $notification);
                }
            }
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationMembershipRequestAcceptEvent $event
     */
    public function onPublicationMembershipRequestAcceptEvent(PublicationMembershipRequestAcceptEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_request_accepted']['key'], $performer, 'Request accepted', $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationMembershipRequestRejectEvent $event
     */
    public function onPublicationMembershipRequestRejectEvent(PublicationMembershipRequestRejectEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_request_rejected']['key'], $performer, 'Request rejected', $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param PublicationMembershipCancelEvent $event
     */
    public function onPublicationMembershipCancelEvent(PublicationMembershipCancelEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();
            $user = $event->getUser();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_membership_cancelled']['key'], $performer, 'Membership cancelled', $publication);
            $this->userNotificationService->notify($user, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }
}