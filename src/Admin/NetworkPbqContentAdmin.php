<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/9/20
 * Time: 5:11 PM
 */

namespace App\Admin;

use App\Entity\NetworkPbqContent;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\File;

class NetworkPbqContentAdmin extends AbstractAdmin
{
    private $image = null;
    private $imageHover = null;

    protected function configureFormFields(FormMapper $formMapper)
    {
        $filePath = $this->getConfigurationPool()->getContainer()->getParameter('network_file_path');
        $fieldOptionsImage = ['required' => false, 'data_class' => null];
        $fieldOptionsImageHover = ['required' => false, 'data_class' => null];

        /**
         * @var NetworkPbqContent $pbqContent
         */
        $pbqContent = $this->getSubject();

        if ($pbqContent->getImage()) {
            $this->image = $pbqContent->getImage();
            $imagePath = $filePath . '/' . $pbqContent->getImage();

            if (file_exists($imagePath)) {
                $fieldOptionsImage['help'] = '<img src="../../../../' . $imagePath . '" class="admin-preview"/>';
            }
        }
        if ($pbqContent->getImageHover()) {
            $this->imageHover = $pbqContent->getImageHover();
            $imagePath = $filePath . '/' . $pbqContent->getImageHover();

            if (file_exists($imagePath)) {
                $fieldOptionsImageHover['help'] = '<img src="../../../../' . $imagePath . '" class="admin-preview"/>';
            }
        }

        $formMapper
            ->add('title', TextType::class)
            ->add('content', CKEditorType::class, [
                'required' => false,
                'config' => ['uiColor' => '#ffffff', 'toolbar' => [['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'Bold', 'Italic', 'Underline', '-', 'Undo', 'Redo', '-', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Format', 'Styles', 'Source', 'Maximize']]]
            ])
            ->add('link')
            ->add('image', FileType::class, $fieldOptionsImage)
            ->add('imageHover', FileType::class, $fieldOptionsImageHover);
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('title')
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
     * @param NetworkPbqContent $object
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

        if ($object->getImageHover()) {
            $imageString = $object->getImageHover();

            $image = new File($imageString);
            $imageName = md5(random_bytes(128)) . '.' . $image->guessExtension();

            $image->move('../public/' . $filePath, $imageName);

            $object->setImageHover($imageName);
        }
        else {
            $object->setImageHover($this->imageHover);
        }
    }
}