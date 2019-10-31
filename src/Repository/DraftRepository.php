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
            $query = $this->createQueryBuilder('d');

            return $query->select('d')
                ->join('d.account', 'a')
                ->where('a = :author')
                ->andWhere('d.id < :fromId')
                ->setParameters(['author' => $account, 'fromId' => $fromDraft->getId()])
                ->setMaxResults($count)
                ->orderBy('d.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('d');

            return $query->select('d')
                ->join('d.account', 'a')
                ->where('a = :author')
                ->setParameters(['author' => $account])
                ->setMaxResults($count)
                ->orderBy('d.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }
}
