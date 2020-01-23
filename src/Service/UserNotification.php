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

class UserNotification
{
    /**
     * @var EntityManager
     */
    private $em;
    private $mercureHub;
    private $mercureSecretKey;

    public function __construct(EntityManagerInterface $em, string $mercureHub, string $mercureSecretKey)
    {
        $this->em = $em;
        $this->mercureHub = $mercureHub;
        $this->mercureSecretKey = $mercureSecretKey;
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
     */
    public function notify(Account $user, Notification $notification, bool $special = false)
    {
        $userNotification = new UN();
        $userNotification->setAccount($user);
        $userNotification->setNotification($notification);
        $userNotification->setIsSpecial($special);

        $this->em->persist($userNotification);
        $this->em->flush();

        //  MERCURE
        $token = (new Builder())
            ->set('mercure', ['publish' => ["http://publiq.site/user/" . $user->getPublicKey()]])
            ->sign(new Sha256(), $this->mercureSecretKey)
            ->getToken();

        $jwtProvider = new StaticJwtProvider($token);
        $publisher = new Publisher($this->mercureHub, $jwtProvider);

        $update = new Update(
            'http://publiq.site/notification',
            json_encode(['notification' => true]),
            ["http://publiq.site/user/" . $user->getPublicKey()]
        );

        // The Publisher service is an invokable object
        $publisher($update);

        return $userNotification;
    }
}