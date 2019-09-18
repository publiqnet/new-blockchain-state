<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/2/19
 * Time: 1:55 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Publication;
use App\Entity\PublicationMember;

/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountRepository extends \Doctrine\ORM\EntityRepository
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
                ->orWhere('a.publicKey like :searchWord')
                ->andWhere('a != :account')
                ->setParameters(['searchWord' => '%' . $searchWord . '%', 'account' => $exception])
                ->orderBy('a.firstName', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('a')
                ->select('a')
                ->where('a.firstName like :searchWord')
                ->orWhere('a.lastName like :searchWord')
                ->orWhere('a.publicKey like :searchWord')
                ->setParameters(['searchWord' => '%' . $searchWord . '%'])
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

    public function getPublicationSubscribers(Publication $publication)
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->join('a.subscriptions', 's')
            ->where('s.publication = :publication')
            ->setParameters(['publication' => $publication])
            ->getQuery()
            ->getResult();
    }

    public function fulltextSearch($searchWord, $count = 5)
    {
        return $this->createQueryBuilder('a')
            ->select("a")
            ->where('MATCH_AGAINST(a.firstName, a.lastName, a.bio, :searchWord \'IN BOOLEAN MODE\') > 0')
            ->setParameter('searchWord', $searchWord)
            ->setMaxResults($count)
            ->getQuery()
            ->getResult();
    }

    public function getPopularAuthors($count = 5, Account $exception = null)
    {
        if ($exception) {
            return $this->createQueryBuilder('a')
                ->select("a, SUM(cu.views) as totalViews, COUNT(cu) as totalArticles")
                ->leftJoin('a.authorContentUnits', 'cu')
                ->where('a != :exception')
                ->having('totalArticles > 0')
                ->setParameter('exception', $exception)
                ->setMaxResults($count)
                ->groupBy('a')
                ->orderBy('totalViews', 'DESC')
                ->getQuery()
                ->getResult('AGGREGATES_HYDRATOR');
        } else {
            return $this->createQueryBuilder('a')
                ->select("a, SUM(cu.views) as totalViews, COUNT(cu) as totalArticles")
                ->leftJoin('a.authorContentUnits', 'cu')
                ->having('totalArticles > 0')
                ->setMaxResults($count)
                ->groupBy('a')
                ->orderBy('totalViews', 'DESC')
                ->getQuery()
                ->getResult('AGGREGATES_HYDRATOR');
        }
    }
}