<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/13/20
 * Time: 4:20 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\File;
use Doctrine\ORM\EntityRepository;

/**
 * AccountRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountRepository extends EntityRepository
{
    /**
     * @param Account $channel
     * @param int $timestamp
     * @return array|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getChannelContributorsCount(Account $channel, int $timestamp = 0)
    {
        $channelQuery = $this->getEntityManager()
            ->createQuery("
                select a1
                from App:Account a1 
                join App:ContentUnit cu with cu.author = a1
                join App:Transaction t with cu = t.contentUnit
                where cu.channel = :channel and t.timeSigned > :timestamp
            ");

        $query = $this->createQueryBuilder('a');
        return $query->select('COUNT(a) as contributorsCount')
            ->where($query->expr()->in('a', $channelQuery->getDQL()))
            ->setParameters(['channel' => $channel, 'timestamp' => $timestamp])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $timestamp
     * @return Account[]
     */
    public function getChannelsSummary(int $timestamp = 0)
    {
        return $this->createQueryBuilder('a')
            ->select('
                a, 
                (select COUNT(cu.id) from App:ContentUnit cu join App:Transaction t with t.contentUnit = cu where cu.channel = a and t.timeSigned > :timestamp) as publishedContentsCount, 
                (select COALESCE(SUM(cuv.viewsCount), 0) from App:ContentUnitViews cuv where cuv.channel = a and cuv.viewsTime > :timestamp) as distributedContentsCount
            ')
            ->where('a.channel = 1')
            ->setParameters(['timestamp' => $timestamp])
            ->orderBy('publishedContentsCount', 'desc')
            ->groupBy('a.id')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    /**
     * @param int $timestamp
     * @return Account[]|null
     */
    public function getStorageSummary(int $timestamp = 0)
    {
        return $this->createQueryBuilder('a')
            ->select('
                a, 
                (select COALESCE(SUM(ssd.count), 0) from App:ServiceStatisticsDetail ssd join App:Transaction t with t.serviceStatistic = ssd.serviceStatistics where ssd.storage = a and t.timeSigned > :timestamp) as distributedContentsCount
            ')
            ->where('a.storage = 1')
            ->setParameters(['timestamp' => $timestamp])
            ->orderBy('distributedContentsCount', 'desc')
            ->groupBy('a.id')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    /**
     * @param int $timestamp
     * @param int $count
     * @return Account[]|null
     */
    public function getTotalRewardSummary(int $timestamp = 0, int $count = 100)
    {
        return $this->createQueryBuilder('a')
            ->select('a, r.rewardType, SUM(r.whole) as totalWhole, SUM(r.fraction) as totalFraction')
            ->join('a.rewards', 'r')
            ->join('r.block', 'b')
            ->where('b.signTime > :timestamp')
            ->andWhere('r.rewardType in (\'miner\', \'channel\', \'author\', \'storage\')')
            ->setParameters(['timestamp' => $timestamp])
            ->addGroupBy('a.id')
            ->addGroupBy('r.rewardType')
            ->addOrderBy('totalWhole', 'DESC')
            ->addOrderBy('totalFraction', 'DESC')
            ->setMaxResults($count)
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    /**
     * @param string $type
     * @return Account[]|null
     */
    public function getActiveNodes(string $type)
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->where('a.' . $type . ' = 1')
            ->andWhere('a.url is not null')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Account[]|null
     */
    public function getActiveMiners()
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->join('a.signedBlocks', 'b')
            ->where('a.url is not null')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param File $file
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getCoverFirstChannel(File $file)
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->join('a.channelContentUnits', 'cu')
            ->join('cu.transaction', 't')
            ->where('cu.cover = :file')
            ->setParameters(['file' => $file])
            ->orderBy('t.timeSigned', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}