<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/28/19
 * Time: 6:53 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\BoostedContentUnit;
use App\Entity\ContentUnit;
use Doctrine\ORM\EntityRepository;

/**
 * BoostedContentUnitSpendingRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BoostedContentUnitSpendingRepository extends EntityRepository
{
    public function getAuthorBoostedArticlesSummary(Account $author)
    {
        return $this->createQueryBuilder('bcus')
            ->select('SUM(bcus.whole) as spentWhole, SUM(bcus.fraction) as spentFraction')
            ->join('bcus.boostedContentUnit', 'bcu')
            ->where('bcu.sponsor = :author')
            ->setParameters(['author' => $author])
            ->getQuery()
            ->getResult();
    }

    public function getBoostedArticleSummary(ContentUnit $article)
    {
        return $this->createQueryBuilder('bcus')
            ->select('SUM(bcus.whole) as spentWhole, SUM(bcus.fraction) as spentFraction')
            ->join('bcus.boostedContentUnit', 'bcu')
            ->where('bcu.contentUnit = :article')
            ->setParameters(['article' => $article])
            ->getQuery()
            ->getResult();
    }

    public function getBoostSummary(BoostedContentUnit $boostedContentUnit)
    {
        return $this->createQueryBuilder('bcus')
            ->select('SUM(bcus.whole) as spentWhole, SUM(bcus.fraction) as spentFraction')
            ->join('bcus.boostedContentUnit', 'bcu')
            ->where('bcu = :article')
            ->setParameters(['article' => $boostedContentUnit])
            ->getQuery()
            ->getResult();
    }
}