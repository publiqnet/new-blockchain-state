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
use App\Entity\Publication;
use App\Entity\Tag;
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
     * @return array|null
     */
    public function getAuthorArticlesCount(Account $account)
    {
        return $this->createQueryBuilder('cu')
            ->select('COUNT(cu)')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere('cu.author = :author')
            ->setParameter('author', $account)
            ->groupBy('cu.contentId')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $account
     * @return array|null
     */
    public function getAuthorArticlesViews(Account $account)
    {
        return $this->createQueryBuilder('cu')
            ->select('SUM(cu.views)')
            ->where('cu.author = :author')
            ->setParameter('author', $account)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $account
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @param bool $self
     * @return array|null
     */
    public function getAuthorArticles(Account $account, int $count = 10, ContentUnit $fromContentUnit = null, $self = false)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->where('cu2.author = :author')
            ->setParameter('author', $account)
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            $query->select('cu, a, t')
                ->join('cu.transaction', 't')
                ->join('cu.author', 'a')
                ->where('cu.content is not null')
                ->andWhere('cu.author = :author')
                ->andWhere('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()));

            if (!$self) {
                $query->andWhere('t.block is not null');
            }

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
                ->where('cu.content is not null')
                ->andWhere('cu.author = :author')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameter('author', $account)
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();

            if (!$self) {
                $query->andWhere('t.block is not null');
            }

            return $query->setParameter('author', $account)
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param Publication $publication
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @return array|null
     */
    public function getPublicationArticles(Publication $publication, int $count = 10, ContentUnit $fromContentUnit = null)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.transaction', 't')
                ->join('cu.author', 'a')
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere('cu.publication = :publication')
                ->andWhere('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['publication' => $publication, 'fromId' => $fromContentUnit->getId()])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.transaction', 't')
                ->join('cu.author', 'a')
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere('cu.publication = :publication')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameter('publication', $publication)
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param Publication $publication
     * @return array|null
     */
    public function getPublicationArticlesCount(Publication $publication)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');

        return $query->select('COUNT(cu.id)')
            ->join('cu.transaction', 't')
            ->join('cu.author', 'a')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere('cu.publication = :publication')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->setParameter('publication', $publication)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Publication $publication
     * @return array|null
     */
    public function getPublicationArticlesTotalViews(Publication $publication)
    {
        return $this->createQueryBuilder('cu')
            ->select('SUM(cu.views)')
            ->where('cu.publication = :publication')
            ->setParameter('publication', $publication)
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
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.author', 'a')
                ->join('cu.transaction', 't')
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere('cu.id < :fromId')
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
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param Tag $tag
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @return array|null
     */
    public function getArticlesByTag(Tag $tag, int $count = 10, ContentUnit $fromContentUnit = null)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.author', 'a')
                ->join('cu.transaction', 't')
                ->join('cu.tags', 'tg')
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere('cu.id < :fromId')
                ->andWhere('tg = :tag')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['fromId' => $fromContentUnit->getId(), 'tag' => $tag])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, a, t')
                ->join('cu.author', 'a')
                ->join('cu.transaction', 't')
                ->join('cu.tags', 'cut')
                ->join('cut.tag', 'tg')
                ->where('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere('tg = :tag')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['tag' => $tag])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param int $count
     * @param $excludes
     * @return array|null
     */
    public function getBoostedArticles(int $count, $excludes)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu')
            ->join('cu.boosts', 'bcu')
            ->where('bcu.startTimePoint <= :date')
            ->andWhere('(bcu.startTimePoint + bcu.hours * 3600) >= :date')
            ->andWhere('cu NOT IN (:excludes)')
            ->setParameters(['date' => $date->getTimestamp(), 'excludes' => $excludes])
            ->setMaxResults($count)
            ->orderBy('RAND()')
            ->groupBy('cu.id')
            ->getQuery()
            ->getResult();
    }

    public function fulltextSearch($searchWord)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select cu3
                from App:ContentUnit cu3 
                join App:ContentUnitTag cut with cut.contentUnit = cu3
                where cut.tag in (select tg from App:Tag tg where tg.name like :tagSearchWord) 
                group by cu3
            ");

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu, a')
            ->join('cu.author', 'a')
            ->join('cu.transaction', 't')
            ->where('MATCH_AGAINST(cu.title, cu.textWithData, :searchWord \'IN BOOLEAN MODE\') > 0')
            ->orWhere($query->expr()->in('cu.id', $preferenceQuery->getDQL()))
            ->andWhere('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->setParameters(['searchWord' => $searchWord, 'tagSearchWord' => '%' . $searchWord . '%'])
            ->orderBy('cu.id', 'desc')
            ->getQuery()
            ->getResult();
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
     * @param Account $user
     * @param int $count
     * @return array|null
     */
    public function getUserPreferredAuthorsArticles(Account $user, int $count = 3)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select a2 
                from App:Account a2 
                join App:UserPreference up with a2 = up.author
                where up.account = :user and up.author is not null
            ");

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu, a, t')
            ->join('cu.author', 'a')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->in('cu.author', $preferenceQuery->getDQL()))
            ->setParameter('user', $user)
            ->setMaxResults($count)
            ->orderBy('cu.id', 'desc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $user
     * @param int $count
     * @return array|null
     */
    public function getUserPreferredTagsArticles(Account $user, int $count = 3)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select cu3
                from App:ContentUnit cu3 
                join App:ContentUnitTag cut with cut.contentUnit = cu3
                where cut.tag in (select tg from App:Tag tg join App:UserPreference up with up.tag = tg where up.account = :user and up.tag is not null) 
                group by cu3
            ");

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu, a, t')
            ->join('cu.author', 'a')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->in('cu', $preferenceQuery->getDQL()))
            ->setParameter('user', $user)
            ->setMaxResults($count)
            ->orderBy('cu.id', 'desc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param ContentUnit $article
     * @param int $count
     * @return array|null
     */
    public function getArticleRelatedArticles(ContentUnit $article, int $count = 3)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->where('cu2.contentId != :contentId')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select cu3
                from App:ContentUnit cu3 
                left join App:ContentUnitTag cut with cut.contentUnit = cu3
                where cu3.author = :user or cut.tag in (select tg from App:Tag tg join App:ContentUnitTag cut1 with cut1.tag = tg where cut1.contentUnit = :article) 
                group by cu3
            ");

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu, a, t')
            ->join('cu.author', 'a')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->in('cu', $preferenceQuery->getDQL()))
            ->setParameters(['user' => $article->getAuthor(), 'article' => $article, 'contentId' => $article->getContentId()])
            ->setMaxResults($count)
            ->orderBy('cu.id', 'desc')
            ->getQuery()
            ->getResult();
    }
}
