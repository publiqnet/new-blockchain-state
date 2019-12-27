<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/24/19
 * Time: 11:34 AM
 */

namespace App\Repository;

use App\Entity\Draft;
use Doctrine\ORM\EntityRepository;

/**
 * DraftFileRepository
 */
class DraftFileRepository extends EntityRepository
{
    /**
     * @param string $uri
     * @param Draft $draft
     * @return array|null
     */
    public function getFileUsagesWithException(string $uri, Draft $draft)
    {
        return $this->createQueryBuilder('df')
            ->select('df')
            ->where('df.uri = :uri')
            ->andWhere('df.draft <> :draft')
            ->setParameters(['uri' => $uri, 'draft' => $draft])
            ->getQuery()
            ->getResult();
    }
}
