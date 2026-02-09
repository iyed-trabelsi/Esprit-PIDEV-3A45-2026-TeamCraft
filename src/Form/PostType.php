<?php

namespace App\Form;

use App\Entity\Post;
use App\Entity\Rubrique;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'required' => false,
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('typePost', ChoiceType::class, [
                'label' => 'Type de post',
                'choices' => [
                    'Discussion' => 'discussion',
                    'Question' => 'question',
                    'Annonce' => 'annonce',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Publié' => 'published',
                    'Archivé' => 'archived',
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image (optionnel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Image valide (JPEG, PNG, GIF, WebP)',
                    ]),
                ],
            ]);

        if ($options['with_rubrique']) {
            $builder->add('rubrique', EntityType::class, [
                'class' => Rubrique::class,
                'choice_label' => 'nomRubrique',
                'label' => 'Rubrique',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'with_rubrique' => true,
        ]);
    }
}
