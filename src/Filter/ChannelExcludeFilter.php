<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/24/20
 * Time: 12:24 PM
 */

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class ChannelExcludeFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        // Check if the entity is ContentUnit
        if ($targetEntity->getReflectionClass()->name != 'App\Entity\ContentUnit') {
            return "";
        }

        return $targetTableAlias . '.channel_id in (select id from account where excluded = 0 and channel = 1) ';
    }
}