<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/22/19
 * Time: 5:23 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Publication;
use App\Entity\PublicationMember;

/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PublicationRepository extends \Doctrine\ORM\EntityRepository
{
    public function getPublications(int $count = 10, Publication $publication = null)
    {
        if ($publication) {
            return $this->createQueryBuilder('p')
                ->select('p')
                ->where('p.id < :id')
                ->setParameters(['id' => $publication->getId()])
                ->orderBy('p.id', 'DESC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('p')
                ->select('p')
                ->orderBy('p.id', 'DESC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        }
    }

    public function getUserPublicationsOwner(Account $account)
    {
        return $this->createQueryBuilder('p')
            ->select('p')
            ->innerJoin('p.members', 'pm')
            ->where('pm.member = :member')
            ->andWhere('pm.status = :status')
            ->setParameters(['member' => $account, 'status' => PublicationMember::TYPES['owner']])
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getUserPublicationsMember(Account $account)
    {
        return $this->createQueryBuilder('p')
            ->select('p, pm.status as memberStatus')
            ->innerJoin('p.members', 'pm')
            ->where('pm.member = :member')
            ->andWhere('pm.status in (:status)')
            ->setParameters(['member' => $account, 'status' => [PublicationMember::TYPES['editor'], PublicationMember::TYPES['contributor']]])
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getUserPublicationsInvitations(Account $account)
    {
        return $this->createQueryBuilder('p')
            ->select('p, pm.status as memberStatus')
            ->innerJoin('p.members', 'pm')
            ->where('pm.member = :member')
            ->andWhere('pm.status in (:status)')
            ->setParameters(['member' => $account, 'status' => [PublicationMember::TYPES['invited_editor'], PublicationMember::TYPES['invited_contributor']]])
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getUserPublicationsRequests(Account $account)
    {
        return $this->createQueryBuilder('p')
            ->select('p, pm.status as memberStatus')
            ->innerJoin('p.members', 'pm')
            ->where('pm.member = :member')
            ->andWhere('pm.status = :status')
            ->setParameters(['member' => $account, 'status' => PublicationMember::TYPES['requested_contributor']])
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }

    public function getUserSubscriptions(Account $user)
    {
        return $this->createQueryBuilder('p')
            ->select('p')
            ->join('p.subscribers', 's')
            ->where('s.subscriber = :user')
            ->setParameters(['user' => $user])
            ->getQuery()
            ->getResult();
    }

    public function fulltextSearch($searchWord)
    {
//        $searchWord = explode(' ', $searchWord);
//        $searchWord = '+'.implode(' +', $searchWord);

        return $this->createQueryBuilder('p')
            ->select("p")
            ->where('MATCH_AGAINST(p.title, p.description, :searchWord \'IN BOOLEAN MODE\') > 0')
            ->setParameter('searchWord', $searchWord)
            ->getQuery()
            ->getResult();
    }

    public function getPopularPublications($count = 5)
    {
        return $this->createQueryBuilder('p')
            ->select("p, SUM(cu.views) as totalViews")
            ->leftJoin('p.contentUnits', 'cu')
            ->setMaxResults($count)
            ->groupBy('p')
            ->orderBy('totalViews', 'DESC')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }
}