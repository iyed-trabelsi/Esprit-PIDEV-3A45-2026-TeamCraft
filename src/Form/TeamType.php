<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Team Name',
                'attr' => ['placeholder' => 'Enter your team name'],
                'required' => true,
            ])
            ->add('logo', FileType::class, [
                'label' => 'Team Logo (PNG/JPG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Please upload a valid PNG or JPG image',
                    ])
                ],
            ])
            ->add('games', ChoiceType::class, [
                'label' => 'Games Played',
                'choices' => [
                    'Valorant' => 'valorant',
                    'League of Legends (LoL)' => 'lol',
                    'World of Warcraft (WoW)' => 'wow',
                    'CS:GO' => 'csgo',
                    'Overwatch' => 'overwatch',
                    'Fortnite' => 'fortnite',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
        ]);
    }
}
