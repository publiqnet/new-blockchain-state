<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/26/20
 * Time: 11:53 AM
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class ChannelAdmin extends AbstractAdmin
{
    protected $pageType;

    public function getPageType()
    {
        return $this->pageType;
    }

    public function __construct($code, $class, $baseControllerName, $type)
    {
        parent::__construct($code, $class, $baseControllerName);

        $this->pageType = $type;
        $this->baseRouteName = 'admin_type_'.$type;
        $this->baseRoutePattern = $type.'_page';
    }

    /**
     * {@inheritDoc}
     */
    public function createQuery($context = 'list')
    {
        $container = $this->getConfigurationPool()->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $em->getFilters()->disable('channel_exclude_filter');

        $query = parent::createQuery($context);
        $query->andWhere(
            $query->expr()->eq($query->getRootAliases()[0] . '.channel', '1')
        );
        return $query;
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection
            ->remove('delete')
            ->remove('edit')
            ->remove('create')
            ->remove('show')
            ->add('exclude', $this->getRouterIdParameter() . '/exclude')
            ->add('include', $this->getRouterIdParameter() . '/include')
        ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('publicKey', null, ['label' => 'Key', 'header_style' => 'width: 300px'])
            ->add('email')
            ->add('firstName')
            ->add('lastName')
            ->add('whole', null, ['label' => 'Balance', 'template' => 'admin/account_balance.html.twig'])
            ->add('channelContentUnits', null, ['label' => 'Articles', 'template' => 'admin/channel_articles_count.html.twig'])
            ->add('url')
            ->add('excluded', null, ['header_style' => 'width: 200px', 'label' => 'Status', 'template' => 'admin/channel_excluded_status.html.twig'])
        ;
    }
}