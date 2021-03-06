<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/2/19
 * Time: 5:10 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountContentUnit;
use App\Entity\ContentUnit;
use Doctrine\ORM\EntityRepository;

/**
 * BoostedContentUnitRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BoostedContentUnitRepository extends EntityRepository
{
    /**
     * @param ContentUnit $contentUnit
     * @return boolean
     */
    public function isContentUnitBoosted(ContentUnit $contentUnit)
    {
        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        $result = $this->createQueryBuilder('bcu')
            ->select('bcu')
            ->where('bcu.startTimePoint <= :date')
            ->andWhere('bcu.endTimePoint >= :date')
            ->andWhere('bcu.contentUnit = :contentUnit')
            ->andWhere('(bcu.cancelled = 0 or bcu.cancelled is null)')
            ->setParameters(['date' => $date->getTimestamp(), 'contentUnit' => $contentUnit])
            ->getQuery()
            ->getResult();

        return ($result ? true: false);
    }

    /**
     * @param Account $author
     * @return boolean
     */
    public function getAuthorBoostedArticles(Account $author)
    {
        return $this->createQueryBuilder('bcu')
            ->select('bcu, cu, a')
            ->join('bcu.contentUnit', 'cu')
            ->join('bcu.sponsor', 'a')
            ->join('cu.authors', 'acu')
            ->where('bcu.sponsor = :author')
            ->orWhere('acu.account = :author')
            ->andWhere('bcu.cancelled = 0')
            ->setParameters(['author' => $author])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Account $author
     * @return array
     */
    public function getAuthorBoostedArticlesSummary(Account $author)
    {
        return $this->createQueryBuilder('bcu')
            ->select('SUM(bcu.whole) as whole, SUM(bcu.fraction) as fraction')
            ->join('bcu.contentUnit', 'cu')
            ->join('bcu.sponsor', 'a')
            ->where('bcu.sponsor = :author')
            ->orWhere('cu.author = :author')
            ->setParameters(['author' => $author])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param ContentUnit $article
     * @return array
     */
    public function getBoostedArticleSummary(ContentUnit $article)
    {
        return $this->createQueryBuilder('bcu')
            ->select('SUM(bcu.whole) as whole, SUM(bcu.fraction) as fraction')
            ->join('bcu.contentUnit', 'cu')
            ->join('bcu.sponsor', 'a')
            ->where('cu = :article')
            ->setParameters(['article' => $article])
            ->getQuery()
            ->getResult();
    }

    /**
     * @param ContentUnit $article
     * @param Account $user
     * @return array
     */
    public function getArticleBoostsForUser(ContentUnit $article, Account $user)
    {
        /**
         * @var AccountContentUnit[] $authors
         */
        $authors = $article->getAuthors();
        $isOwner = false;
        foreach ($authors as $author) {
            if ($user === $author->getAccount()) {
                $isOwner = true;
                break;
            }
        }

        if ($isOwner) {
            return $this->createQueryBuilder('bcu')
                ->select('bcu')
                ->join('bcu.contentUnit', 'cu')
                ->join('bcu.sponsor', 'a')
                ->where('cu = :article')
                ->setParameters(['article' => $article])
                ->getQuery()
                ->getResult();
        } else {
            return $this->createQueryBuilder('bcu')
                ->select('bcu')
                ->join('bcu.contentUnit', 'cu')
                ->join('bcu.sponsor', 'a')
                ->where('cu = :article')
                ->andWhere('a = :user')
                ->setParameters(['article' => $article, 'user' => $user])
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param int $datetime
     * @return array|null
     */
    public function getBoostsConfirmedAfterDate(int $datetime)
    {
        $query = $this->createQueryBuilder('bcu');
        return $query->select('bcu, a')
            ->join('bcu.sponsor', 'a')
            ->join('bcu.transaction', 't')
            ->join('t.block', 'b')
            ->join('bcu.contentUnit', 'cu')
            ->where('b.signTime > :datetime')
            ->setParameters(['datetime' => $datetime])
            ->getQuery()
            ->getResult();
    }
}