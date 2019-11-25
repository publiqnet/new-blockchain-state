<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/18/19
 * Time: 1:58 PM
 */

namespace App\Repository;

use App\Entity\Account;
use App\Entity\ContentUnit;
use Doctrine\ORM\EntityRepository;

/**
 * ContentUnitViewsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ContentUnitViewsRepository extends EntityRepository
{
    public function getArticleViewsPerChannel(ContentUnit $article)
    {
        return $this->createQueryBuilder('cuv')
            ->select('SUM(cuv.viewsCount) as views, ch.publicKey as publicKey')
            ->join('cuv.channel', 'ch')
            ->where('cuv.contentUnit = :contentUnit')
            ->setParameters(['contentUnit' => $article])
            ->groupBy('cuv.channel')
            ->getQuery()
            ->getResult();
    }

    public function getAuthorBoostedArticlesSummary(Account $author)
    {
        return $this->createQueryBuilder('cuv')
            ->select('SUM(cuv.viewsCount) as views, ch.publicKey as publicKey')
            ->join('cuv.contentUnit', 'cu')
            ->join('cuv.channel', 'ch')
            ->where('cu.author = :author')
            ->setParameters(['author' => $author])
            ->groupBy('cuv.channel')
            ->getQuery()
            ->getResult();
    }
}