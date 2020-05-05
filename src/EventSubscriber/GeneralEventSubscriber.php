<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/10/19
 * Time: 4:55 PM
 */

namespace App\EventSubscriber;

use App\Entity\Account;
use App\Entity\ContentUnitTag;
use App\Entity\NotificationType;
use App\Entity\Subscription;
use App\Entity\UserPreference;
use App\Event\ArticleBoostedByOtherEvent;
use App\Event\ArticleNewEvent;
use App\Event\ArticleShareEvent;
use App\Event\PublicationInvitationAcceptEvent;
use App\Event\PublicationInvitationCancelEvent;
use App\Event\PublicationInvitationRejectEvent;
use App\Event\PublicationInvitationRequestEvent;
use App\Event\PublicationMembershipCancelEvent;
use App\Event\PublicationMembershipLeaveEvent;
use App\Event\PublicationMembershipRequestAcceptEvent;
use App\Event\PublicationMembershipRequestCancelEvent;
use App\Event\PublicationMembershipRequestEvent;
use App\Event\PublicationMembershipRequestRejectEvent;
use App\Event\SubscribeUserEvent;
use App\Event\UnsubscribeUserEvent;
use App\Event\UserPreferenceEvent;
use App\Service\UserNotification;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GeneralEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface $container
     */
    private $container;

    /**
     * @var EntityManager
     */
    public $em;

    /**
     * @var UserNotification
     */
    private $userNotificationService;

    /**
     * @var \Swift_Mailer $swiftMailer
     */
    private $swiftMailer;

    /**
     * @var \Twig_Environment $twig
     */
    private $twig;

    public function __construct(ContainerInterface $container, EntityManagerInterface $em, UserNotification $userNotificationService, \Swift_Mailer $swiftMailer, \Twig_Environment $twig)
    {
        $this->container = $container;
        $this->em = $em;
        $this->userNotificationService = $userNotificationService;
        $this->swiftMailer = $swiftMailer;
        $this->twig = $twig;
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
            PublicationMembershipLeaveEvent::NAME => 'onPublicationMembershipLeaveEvent',
            UserPreferenceEvent::NAME => 'onUserPreferenceEvent',
            ArticleNewEvent::NAME => 'onArticleNewEvent',
            ArticleShareEvent::NAME => 'onArticleShareEvent',
            SubscribeUserEvent::NAME => 'onSubscribeUserEvent',
            UnsubscribeUserEvent::NAME => 'onUnsubscribeUserEvent',
            ArticleBoostedByOtherEvent::NAME => 'onArticleBoostedByOtherEvent',
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

    /**
     * @param PublicationMembershipLeaveEvent $event
     */
    public function onPublicationMembershipLeaveEvent(PublicationMembershipLeaveEvent $event)
    {
        try {
            $publication = $event->getPublication();
            $performer = $event->getPerformer();

            $publicationOwner = $this->em->getRepository(Account::class)->getPublicationOwner($publication);

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['publication_membership_cancelled_by_user']['key'], $performer, 'Membership cancelled by User', $publication);
            $this->userNotificationService->notify($publicationOwner, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param ArticleNewEvent $event
     */
    public function onArticleNewEvent(ArticleNewEvent $event)
    {
        try {
            $publisher = $event->getPublisher();
            $article = $event->getArticle();

            //  get subscribers
            /**
             * @var Subscription[] $subscribers
             */
            $subscribers = $publisher->getSubscribers();
            if (count($subscribers)) {
                $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['new_article']['key'], $publisher, $article->getUri());

                foreach ($subscribers as $subscriber) {
                    $this->userNotificationService->notify($subscriber->getSubscriber(), $notification, true);
                }
            }
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param ArticleShareEvent $event
     */
    public function onArticleShareEvent(ArticleShareEvent $event)
    {
        try {
            $article = $event->getArticle();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['share_article']['key'], null, $article->getUri(), null, $article);
            $this->userNotificationService->notify($article->getAuthor(), $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param SubscribeUserEvent $event
     */
    public function onSubscribeUserEvent(SubscribeUserEvent $event)
    {
        try {
            $performer = $event->getPerformer();
            $author = $event->getAuthor();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['subscribe_user']['key'], $performer, "New subscription");
            $this->userNotificationService->notify($author, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param UnsubscribeUserEvent $event
     */
    public function onUnsubscribeUserEvent(UnsubscribeUserEvent $event)
    {
        try {
            $performer = $event->getPerformer();
            $author = $event->getAuthor();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['unsubscribe_user']['key'], $performer, "New unsubscription");
            $this->userNotificationService->notify($author, $notification);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param UserPreferenceEvent $event
     */
    public function onUserPreferenceEvent(UserPreferenceEvent $event)
    {
        try {
            $user = $event->getUser();
            $article = $event->getArticle();

            $articleAuthor = $article->getAuthor();
            $articleTags = $article->getTags();

            //  AUTHOR
            $authorPreference = $this->em->getRepository(UserPreference::class)->findOneBy(['account' => $user, 'author' => $articleAuthor]);
            if (!$authorPreference) {
                $authorPreference = new UserPreference();
                $authorPreference->setAccount($user);
                $authorPreference->setAuthor($articleAuthor);
            }
            $count = $authorPreference->getCount();
            $authorPreference->setCount(++$count);
            $this->em->persist($authorPreference);

            //  TAGS
            if ($articleTags) {
                /**
                 * @var ContentUnitTag $articleTag
                 */
                foreach ($articleTags as $articleTag) {
                    $tagPreference = $this->em->getRepository(UserPreference::class)->findOneBy(['account' => $user, 'tag' => $articleTag->getTag()]);
                    if (!$tagPreference) {
                        $tagPreference = new UserPreference();
                        $tagPreference->setAccount($user);
                        $tagPreference->setTag($articleTag->getTag());
                    }
                    $count = $tagPreference->getCount();
                    $tagPreference->setCount(++$count);
                    $this->em->persist($tagPreference);
                }
            }

            $this->em->flush();
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }

    /**
     * @param ArticleBoostedByOtherEvent $event
     */
    public function onArticleBoostedByOtherEvent(ArticleBoostedByOtherEvent $event)
    {
        try {
            $performer = $event->getPerformer();
            $article = $event->getArticle();

            $notification = $this->userNotificationService->createNotification(NotificationType::TYPES['article_boosted_by_other']['key'], $performer, $article->getUri(), null, $article);
            $this->userNotificationService->notify($article->getAuthor(), $notification, true);

            //  send email
            $backendEndpoint = $this->container->getParameter('backend_endpoint');
            $performerName = trim($performer->getFirstName() . ' ' . $performer->getLastName()) ?? $performer->getPublicKey();

            $emailBody = $this->twig->render(
                'emails/boosted_article.html.twig',
                ['name' => $performerName, 'title' => $article->getTitle(), 'backendEndpoint' => $backendEndpoint]
            );

            $messageObj = (new \Swift_Message('Your story goes viral'))
                ->setFrom('no-reply@publiq.network', 'Slog')
                ->setTo($article->getAuthor()->getEmail())
                ->setBody($emailBody, 'text/html');
            $this->swiftMailer->send($messageObj);
        } catch (\Throwable $e) {
            // ignore all exceptions for now
        }
    }
}