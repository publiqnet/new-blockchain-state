<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/21/19
 * Time: 3:56 PM
 */

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * FileRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom repository methods below.
 */
class FileRepository extends EntityRepository
{
    /**
     * @return array|null
     */
    public function getCoverFilesWithoutThumbnails()
    {
        $query = $this->createQueryBuilder('f');

        return $query->select('f')
            ->join('f.covers', 'cu')
            ->where('f.mimeType in (\'image/png\', \'image/jpeg\')')
            ->andWhere('f.thumbnail is null')
            ->groupBy('f.id')
            ->getQuery()
            ->getResult();
    }
}