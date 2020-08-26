<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 1:17 PM
 */

namespace App\Repository;

use App\Entity\Account;
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

    public function getUserUnreadNotifications(Account $account, int $count = 0, int $fromId = 0)
    {
        $query = $this->createQueryBuilder('un')
            ->select('un, n')
            ->join('un.notification', 'n')
            ->leftJoin('n.contentUnit', 'cu')
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
            ->leftJoin('n.contentUnit', 'cu')
            ->where('un.account = :account')
            ->setParameter('account', $account)
            ->andWhere('un.isSeen = 0');

        return $query
            ->orderBy('un.id', 'desc')
            ->getQuery()
            ->getResult();
    }
}