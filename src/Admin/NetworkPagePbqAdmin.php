<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/9/20
 * Time: 4:17 PM
 */

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NetworkPagePbqAdmin extends AbstractAdmin
{
    /**
     * {@inheritDoc}
     */
    public function createQuery($context = 'list')
    {
        $query = parent::createQuery($context);
        $query->andWhere(
            $query->expr()->eq($query->getRootAliases()[0] . '.slug', ':slug')
        );
        $query->setParameter('slug', 'pbq');
        return $query;
    }

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
                'required' => false,
                'config' => ['uiColor' => '#ffffff', 'toolbar' => [['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'Bold', 'Italic', 'Underline', '-', 'Undo', 'Redo', '-', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Format', 'Styles', 'Source', 'Maximize']]]
            ]);
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
            ->add('created', null, ['header_style' => 'width: 180px'])
            ->add('updated', null, ['label' => 'Last Updated', 'header_style' => 'width: 180px']);
    }
}