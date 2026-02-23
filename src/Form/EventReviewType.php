<?php

namespace App\Form;

use App\Entity\EventReview;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', NumberType::class, [
                'scale' => 1,
                'attr' => [
                    'min' => 0.0,
                    'max' => 5.0,
                    'step' => 0.2
                ],
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Partagez votre expérience...',
                    'rows' => 3
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventReview::class,
            // enable CSRF protection for this form if submitted via standard methods
            'csrf_protection' => true,
        ]);
    }
}
