<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/20/20
 * Time: 1:39 PM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class NetworkFeedbackAdmin extends AbstractAdmin
{
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('create');
        $collection->remove('update');
        $collection->remove('delete');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name', null, ['header_style' => 'width: 220px'])
            ->add('email', null, ['header_style' => 'width: 220px'])
            ->add('phone', null, ['header_style' => 'width: 150px'])
            ->add('company', null, ['header_style' => 'width: 180px'])
            ->add('subject', null, ['header_style' => 'width: 180px'])
            ->add('message', null, ['collapse' => true])
            ->add('created', null, ['header_style' => 'width: 180px']);
    }
}