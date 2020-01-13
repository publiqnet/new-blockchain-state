<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/13/20
 * Time: 11:38 AM
 */

namespace App\Admin;

use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NetworkPageAdmin extends AbstractAdmin
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
        $query = parent::createQuery($context);
        $query->andWhere(
            $query->expr()->eq($query->getRootAliases()[0] . '.slug', ':slug')
        );
        $query->setParameter('slug', $this->pageType);
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

        if ($this->pageType == 'publiq_daemon_mainnet' || $this->pageType == 'publiq_daemon_testnet') {
            $formMapper->add('githubLinkTitle', TextType::class);
            $formMapper->add('githubLink', TextType::class);
            $formMapper->add('dockerLinkTitle', TextType::class);
            $formMapper->add('dockerLink', TextType::class);
        }
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
            ->add('created', null, ['header_style' => 'width: 180px'])
            ->add('updated', null, ['label' => 'Last Updated', 'header_style' => 'width: 180px']);
    }
}