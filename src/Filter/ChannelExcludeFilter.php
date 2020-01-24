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

        // get channels to exclude from parameters
        $excludeChannelsAddresses = $this->getParameter('exclude_channels_addresses');
        $excludeChannelsAddresses = substr($excludeChannelsAddresses, 1, -1);
        if (!$excludeChannelsAddresses) {
            return "";
        }

        $excludeChannelsAddresses = explode(',', $excludeChannelsAddresses);
        $excludeChannelsAddresses = "'" . implode("', '", $excludeChannelsAddresses) . "'";

        return $targetTableAlias . '.channel_id not in (select id from account where public_key in (' . $excludeChannelsAddresses . ')) ';
    }
}