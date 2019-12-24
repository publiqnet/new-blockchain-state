<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 10/31/19
 * Time: 12:25 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Draft;
use Doctrine\ORM\EntityRepository;

/**
 * DraftRepository
 */
class DraftRepository extends EntityRepository
{
    /**
     * @param Account $account
     * @param int $count
     * @param Draft|null $fromDraft
     * @return array|null
     */
    public function getAuthorDrafts(Account $account, int $count = 10, Draft $fromDraft = null)
    {
        if ($fromDraft) {
            return $this->createQueryBuilder('d')
                ->select('d')
                ->join('d.account', 'a')
                ->where('a = :author')
                ->andWhere('d.id < :fromId')
                ->andWhere('d.published = 0')
                ->setParameters(['author' => $account, 'fromId' => $fromDraft->getId()])
                ->setMaxResults($count)
                ->orderBy('d.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('d')
                ->select('d')
                ->join('d.account', 'a')
                ->where('a = :author')
                ->andWhere('d.published = 0')
                ->setParameters(['author' => $account])
                ->setMaxResults($count)
                ->orderBy('d.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }
}
