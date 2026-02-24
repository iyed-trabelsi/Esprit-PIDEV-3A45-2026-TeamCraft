<?php

namespace App\Form;

use App\Entity\Rubrique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RubriqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomRubrique', TextType::class, [
                'label' => 'Nom de la rubrique',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Général'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('topic', TextType::class, [
                'label' => 'Sujet principal',
                'required' => false,
            ])
            ->add('topicSelect', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'Choisir un sujet existant',
                'mapped' => false,
                'required' => false,
                'choices' => array_combine($options['topics'], $options['topics']),
                'placeholder' => 'Sélectionner un sujet ou créer un nouveau',
                'attr' => ['class' => 'form-select bg-black text-white border-secondary mb-2'],
            ])
            ->add('image', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Image de la rubrique (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'form-control bg-black text-white border-secondary'
                ],
                'help' => 'Vous pouvez uploader une image ou utiliser le bouton "Générer avec IA" ci-dessous'
            ]);

        // Lors de la soumission : si un sujet existant est choisi, on l’utilise (liste OU input, pas les deux)
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $rubrique = $event->getData();
            if (!$rubrique instanceof Rubrique) {
                return;
            }
            $form = $event->getForm();
            $selectedTopic = $form->get('topicSelect')->getData();
            $selectedTopic = \is_string($selectedTopic) ? trim($selectedTopic) : $selectedTopic;
            if ($selectedTopic !== null && $selectedTopic !== '') {
                $rubrique->setExistingTopic($selectedTopic);
                $rubrique->setTopic($selectedTopic);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rubrique::class,
            'topics' => [],
        ]);
        $resolver->setAllowedTypes('topics', 'array');
    }
}
