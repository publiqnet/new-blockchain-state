<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/2/19
 * Time: 1:55 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\File;
use App\Entity\Publication;
use App\Entity\PublicationMember;
use Doctrine\ORM\EntityRepository;

/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountRepository extends EntityRepository
{
    /**
     * @param Publication $publication
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPublicationOwner(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status = :status')
            ->setParameters(['publication' => $publication, 'status' => PublicationMember::TYPES['owner']])
            ->getQuery()
            ->getSingleResult('AGGREGATES_HYDRATOR');
    }

    public function getPublicationEditors(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status = :status')
            ->setParameters(['publication' => $publication, 'status' => PublicationMember::TYPES['editor']])
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getPublicationContributors(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status = :status')
            ->setParameters(['publication' => $publication, 'status' => PublicationMember::TYPES['contributor']])
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getPublicationInvitations(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status in (:status)')
            ->setParameters(['publication' => $publication, 'status' => [PublicationMember::TYPES['invited_editor'], PublicationMember::TYPES['invited_contributor']]])
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getPublicationRequests(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status = :status')
            ->setParameters(['publication' => $publication, 'status' => PublicationMember::TYPES['requested_contributor']])
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getPublicationMembers(Publication $publication, $withOwner = false)
    {
        $memberStatuses = [PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']];
        if ($withOwner) {
            $memberStatuses[] = PublicationMember::TYPES['owner'];
        }

        return $this->createQueryBuilder('a')
            ->select('a, pm.status as memberStatus')
            ->join('a.publications', 'pm')
            ->join('pm.publication', 'p')
            ->where('p = :publication')
            ->andWhere('pm.status in (:status)')
            ->setParameters(['publication' => $publication, 'status' => $memberStatuses])
            ->orderBy('pm.status', 'ASC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function searchUsers(string $searchWord, Account $exception = null)
    {
        if ($exception) {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->where('a.firstName like :searchWord')
                ->orWhere('a.lastName like :searchWord')
                ->orWhere('a.publicKey = :searchWordBase')
                ->andWhere('a != :account')
                ->setParameters(['searchWordBase' => $searchWord, 'searchWord' => '%' . $searchWord . '%', 'account' => $exception])
                ->orderBy('a.firstName', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->where('a.firstName like :searchWord')
                ->orWhere('a.lastName like :searchWord')
                ->orWhere('a.publicKey = :searchWordBase')
                ->setParameters(['searchWordBase' => $searchWord, 'searchWord' => '%' . $searchWord . '%'])
                ->orderBy('a.firstName', 'ASC')
                ->getQuery()
                ->getResult();
        }
    }

    public function getUserSubscriptions(Account $user)
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->join('a.subscribers', 's')
            ->where('s.subscriber = :user')
            ->setParameters(['user' => $user])
            ->getQuery()
            ->getResult();
    }

    public function getPublicationSubscribers(Publication $publication, int $count = 10, int $from = 0)
    {
        if ($from) {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->join('a.subscriptions', 's')
                ->where('s.publication = :publication')
                ->setParameters(['publication' => $publication])
                ->setMaxResults($count)
                ->setFirstResult($from)
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->join('a.subscriptions', 's')
                ->where('s.publication = :publication')
                ->setParameters(['publication' => $publication])
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        }
    }
    public function getPublicationSubscribersCount(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('count(a) as totalCount')
            ->join('a.subscriptions', 's')
            ->where('s.publication = :publication')
            ->setParameters(['publication' => $publication])
            ->getQuery()
            ->getResult();
    }

    public function getAuthorSubscribers(Account $author, int $count = 10, int $from = 0)
    {
        if ($from) {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->join('a.subscriptions', 's')
                ->where('s.author = :author')
                ->setParameters(['author' => $author])
                ->setMaxResults($count)
                ->setFirstResult($from)
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->join('a.subscriptions', 's')
                ->where('s.author = :author')
                ->setParameters(['author' => $author])
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        }
    }
    public function getAuthorSubscribersCount(Account $author)
    {
        return $this->createQueryBuilder('a')
            ->select('count(a) as totalCount')
            ->join('a.subscriptions', 's')
            ->where('s.author = :author')
            ->setParameters(['author' => $author])
            ->getQuery()
            ->getResult();
    }

    public function fulltextSearch($searchWord, $count = 5, Account $fromAccount = null)
    {
        if ($fromAccount) {
            return $this->createQueryBuilder('a')
                ->select("a")
                ->where('MATCH_AGAINST(a.firstName, a.lastName, a.bio, :searchWord \'IN BOOLEAN MODE\') > 0')
                ->andWhere('a.id > :fromId')
                ->setParameters(['searchWord' => $searchWord, 'fromId' => $fromAccount->getId()])
                ->addOrderBy('a.id', 'ASC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('a')
                ->select("a")
                ->where('MATCH_AGAINST(a.firstName, a.lastName, a.bio, :searchWord \'IN BOOLEAN MODE\') > 0')
                ->setParameter('searchWord', $searchWord)
                ->addOrderBy('a.id', 'ASC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        }
    }

    public function fulltextSearchCount($searchWord)
    {
        return $this->createQueryBuilder('a')
            ->select("count(a) as totalCount")
            ->where('MATCH_AGAINST(a.firstName, a.lastName, a.bio, :searchWord \'IN BOOLEAN MODE\') > 0')
            ->setParameter('searchWord', $searchWord)
            ->getQuery()
            ->getResult();
    }

    public function getPopularAuthors($count = 5, Account $exception = null, $order = 'totalViews')
    {
        if ($exception) {
            return $this->createQueryBuilder('a')
                ->select("a, SUM(cu.views) as totalViews, COUNT(cu) as totalArticles")
                ->leftJoin('a.authorContentUnits', 'acu')
                ->leftJoin('acu.contentUnit', 'cu')
                ->where('a != :exception')
                ->having('totalArticles > 0')
                ->setParameter('exception', $exception)
                ->setMaxResults($count)
                ->groupBy('a.id')
                ->orderBy($order, 'DESC')
                ->getQuery()
                ->getResult('AGGREGATES_HYDRATOR');
        } else {
            return $this->createQueryBuilder('a')
                ->select("a, SUM(cu.views) as totalViews, COUNT(cu) as totalArticles")
                ->leftJoin('a.authorContentUnits', 'acu')
                ->leftJoin('acu.contentUnit', 'cu')
                ->having('totalArticles > 0')
                ->setMaxResults($count)
                ->groupBy('a.id')
                ->orderBy($order, 'DESC')
                ->getQuery()
                ->getResult('AGGREGATES_HYDRATOR');
        }
    }

    public function getTrendingAuthors($count = 18)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        return $this->createQueryBuilder('a')
            ->select("a, SUM(vpc.viewsCount) as totalViews")
            ->join('a.authorContentUnits', 'acu')
            ->join('acu.contentUnit', 'cu')
            ->join('cu.viewsPerChannel', 'vpc')
            ->where('vpc.viewsTime > :currentTimestamp')
            ->setParameters(['currentTimestamp' => $date->getTimestamp() - 7 * 86400])
            ->setMaxResults($count)
            ->groupBy('a.id')
            ->orderBy('totalViews', 'DESC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getUserRecommendedAuthors(Account $user, $count = 5)
    {
        $subscriptionQuery = $this->getEntityManager()
            ->createQuery("
                select a1
                from App:Account a1 
                join App:Subscription s with s.author = a1
                where s.subscriber = :user
            ");

        $authorPreferenceQuery = $this->getEntityManager()
            ->createQuery("
                select a2 
                from App:Account a2 
                join App:UserPreference up with a2 = up.author
                where up.account = :user and up.author is not null
            ");

        $tagPreferenceQuery = $this->getEntityManager()
            ->createQuery("
                select cu3
                from App:ContentUnit cu3 
                join App:ContentUnitTag cut with cut.contentUnit = cu3
                where cut.tag in (select tg from App:Tag tg join App:UserPreference up1 with up1.tag = tg where up1.account = :user and up1.tag is not null) 
                group by cu3.id
            ");

        $query = $this->createQueryBuilder('a');
        return $query->select("a")
            ->join('a.authorContentUnits', 'acu')
            ->join('acu.contentUnit', 'cu')
            ->where($query->expr()->in('acu.account', $authorPreferenceQuery->getDQL()))
            ->orWhere($query->expr()->in('cu', $tagPreferenceQuery->getDQL()))
            ->andWhere($query->expr()->notIn('a', $subscriptionQuery->getDQL()))
            ->setParameter('user', $user)
            ->setMaxResults($count)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAccountsWithoutThumbnails()
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->where('a.thumbnail is null')
            ->andWhere('a.image is not null')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param File $file
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getFileFirstChannel(File $file)
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->join('a.channelContentUnits', 'cu')
            ->join('cu.transaction', 't')
            ->where('cu.cover = :file')
            ->setParameters(['file' => $file])
            ->setMaxResults(1)
            ->orderBy('t.timeSigned', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return mixed
     */
    public function getAuthorsCount()
    {
        $query = $this->createQueryBuilder('a');
        return $query->select("count(a) as totalAuthors")
            ->join('a.authorContentUnits', 'acu')
            ->groupBy('a')
            ->getQuery()
            ->getResult();
    }

    public function getCurrentTrendingAuthors()
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->where('a.trendingPosition > 0')
            ->getQuery()
            ->getResult();
    }

    public function getNodesByPublicKeysWithException($exceptions = null)
    {
        $query = $this->createQueryBuilder('a');
        $query
            ->select("a")
            ->where('a.storage = 1 or a.channel = 1')
        ;

        if ($exceptions) {
            $query
                ->andWhere('a.publicKey not in (:publicKeys)')
                ->setParameters(['publicKeys' => $exceptions])
            ;
        }

        return $query
            ->getQuery()
            ->getResult();
    }
}