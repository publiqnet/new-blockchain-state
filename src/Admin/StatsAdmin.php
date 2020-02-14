<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/14/20
 * Time: 4:32 PM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Route\RouteCollection;

class StatsAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'stats';
    protected $baseRouteName = 'stats';

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->clearExcept(['list']);
    }
}