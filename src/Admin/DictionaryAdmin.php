<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/9/19
 * Time: 11:04 AM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class DictionaryAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_sort_order' => 'ASC',
        '_sort_by' => 'wordKey',
    ];

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('delete');
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('wordKey', null, ['label' => 'Key'])
            ->add('value');
    }

//    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
//    {
//        $datagridMapper
//            ->add('wordKey');
//    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('wordKey', null, ['label' => 'Key', 'header_style' => 'width: 300px'])
            ->add('value');
    }

    public function postUpdate($object)
    {
        $requestStack = $this->getConfigurationPool()->getContainer()->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        $locale = $request->get('tl');

        $jsonService = $this->getConfigurationPool()->getContainer()->get('json');
        $jsonService->updateJsons($locale);
    }

    public function postPersist($object)
    {
        $requestStack = $this->getConfigurationPool()->getContainer()->get('request_stack');
        $request = $requestStack->getCurrentRequest();
        $locale = $request->get('tl');

        $jsonService = $this->getConfigurationPool()->getContainer()->get('json');
        $jsonService->updateJsons($locale);
    }
}