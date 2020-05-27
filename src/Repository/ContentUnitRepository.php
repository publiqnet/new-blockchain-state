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
use App\Entity\ContentUnitTag;
use App\Entity\Publication;
use App\Entity\Tag;
use Doctrine\ORM\EntityRepository;

/**
 * ContentUnitRepository
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
            ->join('cu.authors', 'acu')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere('acu.account = :author')
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
            ->join('cu.authors', 'acu')
            ->where('acu.account = :author')
            ->setParameter('author', $account)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $account
     * @param int $count
     * @param ContentUnit|null $fromContentUnit
     * @return array|null
     */
    public function getAuthorArticles(Account $account, int $count = 10, ContentUnit $fromContentUnit = null)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->join('cu2.authors', 'acu2')
            ->where('acu2.account = :author')
            ->andWhere('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->setParameter('author', $account)
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');
        if ($fromContentUnit) {
            return $query->select('cu, acu, t')
                ->join('cu.transaction', 't')
                ->join('cu.authors', 'acu')
                ->where('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['author' => $account, 'fromId' => $fromContentUnit->getId()])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            return $query->select('cu, acu, t')
                ->join('cu.transaction', 't')
                ->join('cu.authors', 'acu')
                ->where($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['author' => $account])
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, acu, t')
                ->join('cu.transaction', 't')
                ->join('cu.authors', 'acu')
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

            return $query->select('cu, acu, t')
                ->join('cu.transaction', 't')
                ->join('cu.authors', 'acu')
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');

        return $query->select('COUNT(cu.id)')
            ->join('cu.transaction', 't')
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, acu, t, p')
                ->join('cu.authors', 'acu')
                ->join('cu.transaction', 't')
                ->leftJoin('cu.publication', 'p')
                ->where('cu.id < :fromId')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['fromId' => $fromContentUnit->getId()])
                ->setMaxResults($count)
                ->orderBy('t.timeSigned', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, acu, t, p')
                ->join('cu.authors', 'acu')
                ->join('cu.transaction', 't')
                ->leftJoin('cu.publication', 'p')
                ->where($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setMaxResults($count)
                ->orderBy('t.timeSigned', 'desc')
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        if ($fromContentUnit) {
            $query = $this->createQueryBuilder('cu');

            return $query->select('cu, acu, t')
                ->join('cu.authors', 'acu')
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

            return $query->select('cu, acu, t')
                ->join('cu.authors', 'acu')
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
    public function getBoostedArticles(int $count, $excludes = null)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');
        if ($excludes) {
            return $query->select('cu')
                ->join('cu.boosts', 'bcu')
                ->where('bcu.startTimePoint <= :date')
                ->andWhere('bcu.cancelled = 0')
                ->andWhere('bcu.endTimePoint >= :date')
                ->andWhere('cu NOT IN (:excludes)')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['date' => $date->getTimestamp(), 'excludes' => $excludes])
                ->setMaxResults($count)
                ->orderBy('RAND()')
                ->groupBy('cu.id')
                ->getQuery()
                ->getResult();
        } else {
            return $query->select('cu')
                ->join('cu.boosts', 'bcu')
                ->where('bcu.startTimePoint <= :date')
                ->andWhere('bcu.cancelled = 0')
                ->andWhere('bcu.endTimePoint >= :date')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['date' => $date->getTimestamp()])
                ->setMaxResults($count)
                ->orderBy('RAND()')
                ->groupBy('cu.id')
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param int $count
     * @return array|null
     */
    public function getBoostedArticlesWithCover(int $count)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu')
            ->join('cu.boosts', 'bcu')
            ->where('bcu.startTimePoint <= :date')
            ->andWhere('bcu.endTimePoint >= :date')
            ->andWhere('cu.cover is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->setParameters(['date' => $date->getTimestamp()])
            ->setMaxResults($count)
            ->orderBy('RAND()')
            ->groupBy('cu.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $count
     * @return array|null
     */
    public function getHighlights(int $count)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu')
            ->join('cu.boosts', 'bcu')
            ->where('bcu.startTimePoint <= :date')
            ->andWhere('bcu.endTimePoint >= :date')
            ->andWhere('cu.highlight = 1')
            ->setParameters(['date' => $date->getTimestamp()])
            ->setMaxResults($count)
            ->orderBy('RAND()')
            ->groupBy('cu.id')
            ->getQuery()
            ->getResult();
    }

    public function fulltextSearch($searchWord, $count = 5, ContentUnit $fromContentUnit = null)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
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

        if ($fromContentUnit) {
            return $query->select('cu, acu')
                ->join('cu.authors', 'acu')
                ->join('cu.transaction', 't')
                ->where('MATCH_AGAINST(cu.title, cu.textWithData, :searchWord \'IN BOOLEAN MODE\') > 0')
                ->orWhere($query->expr()->in('cu.id', $preferenceQuery->getDQL()))
                ->andWhere('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->andWhere('cu.id < :fromId')
                ->setParameters(['fromId' => $fromContentUnit->getId(), 'searchWord' => $searchWord, 'tagSearchWord' => '%' . $searchWord . '%'])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        } else {
            return $query->select('cu, acu')
                ->join('cu.authors', 'acu')
                ->join('cu.transaction', 't')
                ->where('MATCH_AGAINST(cu.title, cu.textWithData, :searchWord \'IN BOOLEAN MODE\') > 0')
                ->orWhere($query->expr()->in('cu.id', $preferenceQuery->getDQL()))
                ->andWhere('t.block is not null')
                ->andWhere('cu.content is not null')
                ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
                ->setParameters(['searchWord' => $searchWord, 'tagSearchWord' => '%' . $searchWord . '%'])
                ->setMaxResults($count)
                ->orderBy('cu.id', 'desc')
                ->getQuery()
                ->getResult();
        }
    }

    public function getArticleHistory(ContentUnit $article, $previous = false)
    {
        $query = $this->createQueryBuilder('cu')
            ->select('cu, acu')
            ->join('cu.authors', 'acu')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere('cu.contentId = :contentId');

        if ($previous) {
            $query
                ->andWhere('cu.id < :id')
                ->orderBy('cu.id', 'desc');
        } else {
            $query
                ->andWhere('cu.id > :id')
                ->orderBy('cu.id', 'asc');
        }

        return $query
            ->setParameters(['id' => $article->getId(), 'contentId' => $article->getContentId()])
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select a2 
                from App:Account a2 
                join App:UserPreference up with a2 = up.author
                where up.account = :user and up.author is not null
            ");

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu, acu, t')
            ->join('cu.authors', 'acu')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->in('acu.account', $preferenceQuery->getDQL()))
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
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $preferenceQuery = $this->getEntityManager()
            ->createQuery("
                select cu3
                from App:ContentUnit cu3 
                join App:ContentUnitTag cut with cut.contentUnit = cu3
                where cut.tag in (select tg from App:Tag tg join App:UserPreference up with up.tag = tg where up.account = :user and up.tag is not null) 
                group by cu3.id
            ");

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu, acu, t')
            ->join('cu.authors', 'acu')
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
        $params = ['user' => $article->getAuthor(), 'contentId' => $article->getContentId()];
        $tagQueryString = '';

        /**
         * @var ContentUnitTag[] $tags
         */
        $tags = $article->getTags();
        if ($tags) {
            $tagIndex = 1;
            foreach ($tags as $tag) {
                $params['tag' . $tagIndex] = $tag->getTag();
                $tagQueryString .= ' or cut.tag = :tag' . $tagIndex;
            }
        }

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
                left join App:AccountContentUnit acu2 with cut.contentUnit = cu3
                where acu2.account = :user " . $tagQueryString . "
                group by cu3
            ");

        $query = $this->createQueryBuilder('cu');

        return $query->select('cu, acu, t')
            ->join('cu.authors', 'acu')
            ->join('cu.transaction', 't')
            ->where('t.block is not null')
            ->andWhere('cu.content is not null')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->in('cu', $preferenceQuery->getDQL()))
            ->setParameters($params)
            ->setMaxResults($count)
            ->orderBy('RAND()')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $account
     * @return boolean
     */
    public function updateSocialImageStatus(Account $account)
    {
        $this->createQueryBuilder('cu')
            ->update()
            ->join('cu.authors', 'acu')
            ->where('acu.account= :author')
            ->setParameter('author', $account)
            ->set('cu.updateSocialImage', true)
            ->getQuery()
            ->execute();

        return true;
    }

    /**
     * @param Account $account
     * @return array|null
     */
    public function getAuthorRelatedBoosts(Account $account)
    {
        $query = $this->createQueryBuilder('cu');
        return $query->select('cu')
            ->join('cu.boosts', 'bcu')
            ->join('cu.authors', 'acu')
            ->where('bcu.sponsor = :sponsor')
            ->orWhere('acu.account= :sponsor')
            ->setParameters(['sponsor' => $account])
            ->groupBy('cu')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $account
     * @return array|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getAuthorNonBoostedRandomArticle(Account $account)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery
            ->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->join('cu2.authors', 'acu2')
            ->where('acu2.account = :author')
            ->andWhere('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->setParameter('author', $account)
            ->groupBy('cu2.contentId');

        $subQuery2 = $this->createQueryBuilder('cu3');
        $subQuery2
            ->select('cu3.id')
            ->join('cu3.boosts', 'cub')
            ->where('cub.startTimePoint < :date')
            ->andWhere('cub.endTimePoint > :date')
            ->setParameters(['date' => $date->getTimestamp()])
            ->groupBy('cu3.id');

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu, acu, t')
            ->join('cu.transaction', 't')
            ->join('cu.authors', 'acu')
            ->where($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->andWhere($query->expr()->notIn('cu.id', $subQuery2->getDQL()))
            ->setParameters(['author' => $account, 'date' => $date->getTimestamp()])
            ->setMaxResults(1)
            ->orderBy('RAND()')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $datetime
     * @return array|null
     */
    public function getArticlesConfirmedAfterDate(int $datetime)
    {
        $subQuery = $this->createQueryBuilder('cu2');
        $subQuery->select('max(cu2.id)')
            ->join('cu2.transaction', 't2')
            ->where('t2.block is not null')
            ->andWhere('cu2.content is not null')
            ->groupBy('cu2.contentId');

        $query = $this->createQueryBuilder('cu');
        return $query->select('cu, acu')
            ->join('cu.authors', 'acu')
            ->join('cu.transaction', 't')
            ->join('t.block', 'b')
            ->where('b.signTime > :datetime')
            ->andWhere($query->expr()->in('cu.id', $subQuery->getDQL()))
            ->setParameters(['datetime' => $datetime])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array|null
     */
    public function getArticlesSummary()
    {
        return $this->createQueryBuilder('cu')
            ->select('count(cu) as totalArticles, sum(cu.views) as totalViews')
            ->getQuery()
            ->getResult();
    }
}
