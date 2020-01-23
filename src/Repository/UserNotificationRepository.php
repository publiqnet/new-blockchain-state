<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 1:17 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\UserNotification;
use Doctrine\ORM\EntityRepository;

/**
 * Class UserNotificationRepository
 * @package App\Repository
 */
class UserNotificationRepository extends EntityRepository
{
    public function markAllAsRead(Account $account)
    {
        $query = $this->createQueryBuilder('un')
            ->update()
            ->where('un.account = :account')
            ->setParameter('account', $account)
            ->andWhere('un.isRead = 0')
            ->set('un.isRead', true)
            ->getQuery();
        return $query->execute();
    }

    public function markAllAsSeen(Account $account)
    {
        $query = $this->createQueryBuilder('un')
            ->update()
            ->where('un.account = :account')
            ->setParameter('account', $account)
            ->andWhere('un.isSeen = 0')
            ->set('un.isSeen', true)
            ->getQuery();
        return $query->execute();
    }

    public function getUserNotifications(Account $account, int $count = 0, int $fromId = 0)
    {
        $query = $this->createQueryBuilder('un')
            ->select('un, n')
            ->join('un.notification', 'n')
            ->where('un.account = :account')
            ->setParameter('account', $account);

        if ($fromId) {
            $query
                ->andWhere('un.id < :id')
                ->setParameter('id', $fromId);
        }

        if ($count) {
            $query
                ->setMaxResults($count);
        }

        return $query
            ->orderBy('un.id', 'desc')
            ->getQuery()
            ->getResult();
    }

    public function getUserUnreadNotifications(Account $account, int $count = 0, int $fromId = 0)
    {
        $query = $this->createQueryBuilder('un')
            ->select('un, n')
            ->join('un.notification', 'n')
            ->where('un.account = :account')
            ->setParameter('account', $account)
            ->andWhere('un.isRead = 0');

        if ($fromId) {
            $query
                ->andWhere('un.id < :id')
                ->setParameter('id', $fromId);
        }

        if ($count) {
            $query
                ->setMaxResults($count);
        }

        return $query
            ->orderBy('un.id', 'desc')
            ->getQuery()
            ->getResult();
    }

    public function getUserUnseenNotifications(Account $account)
    {
        $query = $this->createQueryBuilder('un')
            ->select('un, n')
            ->join('un.notification', 'n')
            ->where('un.account = :account')
            ->setParameter('account', $account)
            ->andWhere('un.isSeen = 0');

        return $query
            ->orderBy('un.id', 'desc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $user
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getNumberOfUnreadByUser(Account $user)
    {
        return intval(
            $this->getEntityManager()->createQueryBuilder()
                ->from(UserNotification::class, 'un')
                ->select('COUNT(un.id)')
                ->where('un.user = :user')
                ->setParameter('user', $user)
                ->andWhere('un.isRead = 0')
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    public function getNewNotificationsByUser(Account $user, int $upToId)
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(UserNotification::class, 'un')
            ->select('un')
            ->where('un.user = :user')
            ->setParameter('user', $user)
            ->andWhere('un.isRead = :is_read')
            ->setParameter('is_read', false)
            ->andWhere('un.id > :upToId')
            ->setParameter('upToId', $upToId)
            ->orderBy('un.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}