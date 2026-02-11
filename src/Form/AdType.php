<?php

namespace App\Form;

use App\Entity\Ad;
use App\Entity\Category;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class AdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('price')
            ->add('category' , EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
            ])
            ->add('city', null, [
            'label' => 'Ville',
            'attr' => ['placeholder' => 'Ex: Lézignan-Corbières']
             ])
            ->add('showPhone', CheckboxType::class, [
                'label'    => 'Afficher mon numéro de téléphone sur l\'annonce',
                 'required' => false,
            ])
            
            ->add('imageFiles', FileType::class, [
            'label' => 'Ajouter des photos',
            'multiple' => true,
            'mapped' => false, // Important : ce n'est pas lié directement à un champ de la base
            'required' => false,
            'attr' => ['accept' => 'image/*']
            ]) ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ad::class,
        ]);
    }
}
