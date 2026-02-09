<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Place;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomEvenement', null, ['required' => false])
            ->add('typeEvenement', null, ['required' => false])
            ->add('dateDebut', null, ['required' => false])
            ->add('dateFin', null, ['required' => false])
            ->add('status', null, ['required' => false])
            ->add('place', EntityType::class, [
                'class' => Place::class,
                'choice_label' => 'nomPlace',
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
