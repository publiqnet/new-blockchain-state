<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/11/19
 * Time: 1:41 PM
 */

namespace App\Repository;

use App\Entity\Account;
use Doctrine\ORM\EntityRepository;

/**
 * Class UserNotificationRepository
 * @package App\Repository
 */
class NotificationRepository extends EntityRepository
{
    public function getUserNotifications(Account $account, int $count = 0, int $fromId = 0)
    {
        $query = $this->createQueryBuilder('n')
            ->select('n, un.isRead, un.id')
            ->join('n.userNotifications', 'un')
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
}