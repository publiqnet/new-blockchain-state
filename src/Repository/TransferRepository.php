<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/4/19
 * Time: 2:14 PM
 */

namespace App\Repository;

use App\Entity\Block;
use Doctrine\ORM\EntityRepository;

/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TransferRepository extends EntityRepository
{
    /**
     * @param Block $block
     * @return array|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getBlockTransfersSummary(Block $block)
    {
        return $this->createQueryBuilder('t')
            ->select('SUM(t.whole) as totalWhole, SUM(t.fraction) as totalFraction')
            ->join('t.transaction', 'tr')
            ->where('tr.block = :block')
            ->setParameters(['block' => $block])
            ->getQuery()
            ->getOneOrNullResult();
    }
}