<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label' => 'Votre commentaire',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Écrivez votre réponse...'],
            ])
            ->add('image', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Ajouter une image (PNG, JPG, GIF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'audio/mpeg',
                            'audio/webm',
                            'audio/ogg',
                            'audio/wav',
                            'audio/mp4',
                            'audio/x-m4a',
                            'video/webm', // Often used for webm audio
                            'video/ogg',
                            'application/ogg',
                            'application/octet-stream', // Fallback for blobs
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (PNG, JPG, GIF, WebP) ou un fichier audio (MP3, WEBM, OGG, WAV, M4A).',
                    ])
                ],
                'attr' => ['class' => 'form-control bg-black text-white border-secondary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}
