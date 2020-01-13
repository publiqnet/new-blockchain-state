<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/13/20
 * Time: 4:58 PM
 */

namespace App\Hydrator;

use Doctrine\ORM\Internal\Hydration\ObjectHydrator;

/**
 * CUSTOM HYDRATOR
 * Use this only when selecting an entity first, aggregates later
 * and you want those aggregates inside the entity.
 *
 * Class AggregatesHydrator
 * @package AppBundle\Hydrator
 */
class AggregatesHydrator extends ObjectHydrator
{
    /**
     * @inheritdoc
     */
    public function hydrateAllData()
    {
        $result = parent::hydrateAllData();
        array_walk($result, function (&$el) {
            $entity = $el["0"];
            $keys = array_keys($el);
            for ($i = 1; $i < count($keys); $i++) {
                $keyName = $keys[$i];
                $methodName = 'set' . ucfirst($keyName);
                if (method_exists($entity, $methodName)) {
                    $entity->$methodName($el[$keyName]);
                }
            }
            $el = $entity;
        });
        return $result;
    }
}