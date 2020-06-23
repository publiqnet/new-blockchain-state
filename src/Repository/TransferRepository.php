<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/23/2020
 * Time: 2:49 PM
 */

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * TransferRepository
 */
class TransferRepository extends EntityRepository
{
    /**
     * @param int $datetime
     * @return array|null
     */
    public function getTransfersConfirmedAfterDate(int $datetime)
    {
        $query = $this->createQueryBuilder('tr');
        return $query->select('tr')
            ->join('tr.transaction', 't')
            ->join('t.block', 'b')
            ->where('b.signTime > :datetime')
            ->setParameters(['datetime' => $datetime])
            ->getQuery()
            ->getResult();
    }
}
