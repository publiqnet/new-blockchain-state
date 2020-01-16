<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/27/19
 * Time: 1:09 PM
 */

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NetworkSupportContentAdmin extends AbstractAdmin
{
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('create');
        $collection->remove('delete');
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('title', TextType::class)
            ->add('content', CKEditorType::class, [
                'config' => ['uiColor' => '#ffffff', 'toolbar' => [['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'Bold', 'Italic', 'Underline', '-', 'Undo', 'Redo', '-', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Format', 'Styles', 'TextColor', 'Source', 'Maximize']]]
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('title');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
            ->add('created', null, ['header_style' => 'width: 180px'])
            ->add('updated', null, ['label' => 'Last Updated', 'header_style' => 'width: 180px']);
    }
}