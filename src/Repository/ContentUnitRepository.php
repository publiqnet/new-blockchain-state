<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/14/19
 * Time: 12:07 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\ContentUnit;
use Doctrine\ORM\EntityRepository;

/**
 * ContentUnitRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ContentUnitRepository extends EntityRepository
{
    /**
     * @param Account $account
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @param bool $self
     * @return array|null
     */
    public function getAuthorArticles(Account $account, int $count = 10, ContentUnit $fromContentUnit = null, $self = false)
    {
        if ($self) {
            $subQuery = $this->createQueryBuilder('cu2');
            $subQuery
                ->select('max(cu2.id)')
                ->where('cu2.author = :author')
                ->andWhere('cu2.content is not null')
                ->setParameter('author', $account)
                ->groupBy('cu2.contentId');
        } else {
            $subQuery = $this->createQueryBuilder('cu2');
            $subQuery
                ->select('max(cu2.id)')
                ->join('cu2.transaction', 't2')
                ->where('cu2.author = :author')
                ->andWhere('t2.block is not null')
                ->andWhere('cu2.content is not null')
                ->setParameter('author', $account)
                ->groupBy('cu2.contentId');
        }

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            $query->select('cu, a, t')
                ->join('cu.transaction', 't')
                ->join('cu.author', 'a')
                ->where('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()));

            return $query->setParameters(['author' => $account, 'fromId' => $fromContentUnit->getId()])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('cu');

            $query->select('cu, a, t')
                ->join('cu.transaction', 't')
                ->join('cu.author', 'a')
                ->where($query->expr()->in('cu.id', $subQuery->getDQL()));

            return $query->setParameters(['author' => $account])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    public function getArticleHistory(ContentUnit $article, $previous = false)
    {
        $query = $this->createQueryBuilder('cu')
            ->select('cu, a')
            ->join('cu.author', 'a')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere('cu.contentId = :contentId')
            ->andWhere('cu.channel = :channel');

        if ($previous) {
            $query
                ->andWhere('cu.id < :id')
                ->orderBy('cu.id', 'desc')
            ;
        } else {
            $query
                ->andWhere('cu.id > :id')
                ->orderBy('cu.id', 'asc')
            ;
        }

        return $query
            ->setParameters(['id' => $article->getId(), 'contentId' => $article->getContentId(), 'channel' => $article->getChannel()])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @return array|null
     */
    public function getArticles(int $count = 10, ContentUnit $fromContentUnit = null)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.author', 'a')
                ->join('cu.transaction', 't')
                ->where('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['fromId' => $fromContentUnit->getId()])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.author', 'a')
                ->join('cu.transaction', 't')
                ->where($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }
}
