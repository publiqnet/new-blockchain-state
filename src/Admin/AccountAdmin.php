<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 10/31/19
 * Time: 2:09 PM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class AccountAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_sort_order' => 'DESC',
        '_sort_by' => 'id',
    ];

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('delete');
        $collection->remove('edit');
        $collection->remove('create');
    }

//    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
//    {
//        $datagridMapper
//            ->add('publicKey')
//            ->add('email')
//            ->add('channel')
//            ->add('storage')
//        ;
//    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('publicKey', null, ['label' => 'Key', 'header_style' => 'width: 300px'])
            ->add('email')
            ->add('firstName')
            ->add('lastName')
            ->add('whole', null, ['label' => 'Balance', 'template' => 'admin/account_balance.html.twig'])
            ->add('authorContentUnits', null, ['label' => 'Articles', 'template' => 'admin/account_articles_count.html.twig'])
            ->add('channel')
            ->add('storage')
        ;
    }
}