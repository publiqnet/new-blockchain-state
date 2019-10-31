<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 10/31/19
 * Time: 5:25 PM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class PublicationAdmin extends AbstractAdmin
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

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('slug')
        ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('slug', null, ['header_style' => 'width: 300px'])
            ->add('title')
            ->add('description', null, ['collapse' => true])
            ->add('members', null, ['header_style' => 'width: 300px', 'label' => 'Members', 'template' => 'admin/publication_owner.html.twig'])
            ->add('subscribers', null, ['header_style' => 'width: 150px', 'label' => 'Subscribers', 'template' => 'admin/publication_subscribers_count.html.twig'])
            ->add('contentUnits', null, ['header_style' => 'width: 150px', 'label' => 'Articles', 'template' => 'admin/publication_articles_count.html.twig'])
        ;
    }
}