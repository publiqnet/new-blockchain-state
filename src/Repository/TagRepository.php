<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/6/19
 * Time: 5:47 PM
 */

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * TagRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TagRepository extends EntityRepository
{
    /**
     * @return array|null
     */
    public function getTagsByPopularity()
    {
        return $this->createQueryBuilder('t')
            ->select('t, COUNT(cu) as contentUnitCount')
            ->leftJoin('t.contentUnits', 'cu')
            ->addOrderBy('contentUnitCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->groupBy('t.id')
            ->getQuery()
            ->getResult('AGGREGATES_HYDRATOR');
    }
}