<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 12:09 PM
 */

namespace App\Admin;

use App\Entity\NetworkBrandTypographyContent;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\File;

class NetworkBrandTypographyContentAdmin extends AbstractAdmin
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
         * @var NetworkBrandTypographyContent $object
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
            ->add('content', CKEditorType::class, [
                'required' => false,
                'config' => ['uiColor' => '#ffffff', 'toolbar' => [['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'Bold', 'Italic', 'Underline', '-', 'Undo', 'Redo', '-', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Format', 'Styles', 'TextColor', 'Source', 'Maximize']]]
            ])
            ->add('image', FileType::class, $fieldOptionsImage);
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
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
     * @param NetworkBrandTypographyContent $object
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