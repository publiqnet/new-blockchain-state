<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/29/19
 * Time: 12:56 PM
 */

namespace App\Hydrator;

use Doctrine\ORM\Internal\Hydration\AbstractHydrator, PDO;

class ColumnHydrator extends AbstractHydrator
{
    protected function hydrateAllData()
    {
        return $this->_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}