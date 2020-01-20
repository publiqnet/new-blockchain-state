<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/20/20
 * Time: 1:23 PM
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class NetworkFeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['constraints' => new NotBlank()])
            ->add('email', EmailType::class, ['constraints' => new Email()])
            ->add('phone', TextType::class)
            ->add('company', TextType::class)
            ->add('subject', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('message', TextareaType::class, ['constraints' => [new NotBlank()]]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }
}