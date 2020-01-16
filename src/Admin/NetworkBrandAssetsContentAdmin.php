<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 4:13 PM
 */

namespace App\Admin;

use App\Entity\NetworkBrandAssetsContent;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\File;

class NetworkBrandAssetsContentAdmin extends AbstractAdmin
{
    private $image = null;

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('create');
        $collection->remove('delete');
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $filePath = $this->getConfigurationPool()->getContainer()->getParameter('network_file_path');
        $fieldOptionsImage = ['required' => false, 'data_class' => null];

        /**
         * @var NetworkBrandAssetsContent $object
         */
        $object = $this->getSubject();

        if ($object->getImage()) {
            $this->image = $object->getImage();
            $imagePath = $filePath . '/' . $object->getImage();

            if (file_exists($imagePath)) {
                $fieldOptionsImage['help'] = '<img src="../../../../' . $imagePath . '" class="admin-preview"/>';
            }
        }

        $formMapper
            ->add('title', TextType::class)
            ->add('subtitle', TextType::class, ['required' => false])
            ->add('link', TextType::class, ['required' => false])
            ->add('image', FileType::class, $fieldOptionsImage);
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
            ->add('subtitle')
            ->add('link')
            ->add('created', null, ['header_style' => 'width: 180px'])
            ->add('updated', null, ['label' => 'Last Updated', 'header_style' => 'width: 180px']);
    }

    /**
     * @param $object
     * @throws \Exception
     */
    public function prePersist($object)
    {
        $this->manageFileUpload($object);
    }

    /**
     * @param $object
     * @throws \Exception
     */
    public function preUpdate($object)
    {
        $this->manageFileUpload($object);
    }

    /**
     * @param NetworkBrandAssetsContent $object
     * @throws \Exception
     */
    private function manageFileUpload($object)
    {
        $filePath = $this->getConfigurationPool()->getContainer()->getParameter('network_file_path');

        if ($object->getImage()) {
            $imageString = $object->getImage();

            $image = new File($imageString);
            $imageName = md5(random_bytes(128)) . '.' . $image->guessExtension();

            $image->move('../public/' . $filePath, $imageName);

            $object->setImage($imageName);
        } else {
            $object->setImage($this->image);
        }
    }
}