<?php

namespace App\Form;

use App\Entity\MusicTrack;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class MusicUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Music Title *',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter the title...',
                    'class' => 'form-control',
                    'required' => 'required'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Title is required'
                    ])
                ]
            ])
            ->add('musicFile', FileType::class, [
                'label' => 'Audio File (MP3, WAV, OGG)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'audio/mpeg',
                            'audio/mp3',
                            'audio/wav',
                            'audio/x-wav',
                            'audio/ogg',
                            'audio/vorbis',
                            'audio/x-mpeg-3',
                            'video/mpeg'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid audio file (MP3, WAV, OGG)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'audio/*, .mp3, .wav, .ogg'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MusicTrack::class,
        ]);
    }
}
