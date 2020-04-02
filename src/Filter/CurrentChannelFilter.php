<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/2/2020
 * Time: 4:41 PM
 */

namespace App\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class CurrentChannelFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        // Check if the entity is ContentUnit
        if ($targetEntity->getReflectionClass()->name != 'App\Entity\ContentUnit') {
            return "";
        }

        // get current channel address from parameters
        $channelAddress = $this->getParameter('channel_address');
        $channelAddress = substr($channelAddress, 1, -1);
        if (!$channelAddress) {
            return "";
        }

        return $targetTableAlias . '.channel_id in (select id from account where public_key = ' . $channelAddress . ') ';
    }
}