<?php

namespace App\Form;

use App\Entity\Offer;
use App\Entity\Team;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => 'Title',
                'required' => false,
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('game', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Valorant' => 'VALORANT',
                    'League of Legends' => 'LOL',
                    'CS2' => 'CS2',
                    'Overwatch 2' => 'OVERWATCH',
                ],
                'attr' => ['class' => 'form-select bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('role', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('rank', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('nbPlayerRecruited', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('dateExpiration', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25', 'rows' => 4]
            ])
            ->add('poster', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Poster (Image file)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File([
                        'maxSize' => '2048k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image',
                    ])
                ],
                'attr' => ['class' => 'form-control bg-dark text-white border-secondary border-opacity-25']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offer::class,
        ]);
    }
}
