<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Place;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomEvenement', null, ['required' => false])
            ->add('typeEvenement', null, ['required' => false])
            ->add('dateDebut', null, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('dateFin', null, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('status', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices'  => [
                    'Ouvert' => 'open',
                    'Fermé' => 'closed',
                    'Terminé' => 'over',
                ],
                'required' => false,
                'placeholder' => 'Choisir un statut',
            ])
            ->add('place', EntityType::class, [
                'class' => Place::class,
                'choice_label' => 'nomPlace',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image de l\'événement (facultatif)',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
