<?php

namespace App\Entity;

use App\Repository\RubriqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RubriqueRepository::class)]
#[ORM\Table(name: 'teamcraft_rubrique')]
class Rubrique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le nom de la rubrique est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nomRubrique = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private ?string $description = null;

    // 🔹 Nouveau sujet (NON obligatoire si un sujet existant est choisi)
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le sujet ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $topic = null;

    // 🔹 Sujet existant (champ formulaire seulement, PAS en base)
    private ?string $existingTopic = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: ['active', 'archived'],
        message: 'L\'état doit être actif ou archivé.'
    )]
    private ?string $etat = 'active';

    #[ORM\Column]
    private int $nbPosts = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $auteur = null;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(mappedBy: 'rubrique', targetEntity: Post::class, orphanRemoval: true)]
    private Collection $posts;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->dateCreation = new \DateTime();
    }

    // ======================
    // VALIDATION CONDITIONNELLE
    // ======================
    #[Assert\Callback]
    public function validateTopic(ExecutionContextInterface $context): void
    {
        if (empty($this->existingTopic) && empty($this->topic)) {
            $context->buildViolation(
                'Vous devez choisir un sujet existant ou saisir un nouveau sujet.'
            )
            ->atPath('topic')
            ->addViolation();
        }
    }

    // ======================
    // GETTERS & SETTERS
    // ======================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomRubrique(): ?string
    {
        return $this->nomRubrique;
    }

    public function setNomRubrique(?string $nomRubrique): static
    {
        $this->nomRubrique = $nomRubrique;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): static
    {
        $this->topic = $topic;
        return $this;
    }

    public function getExistingTopic(): ?string
    {
        return $this->existingTopic;
    }

    public function setExistingTopic(?string $existingTopic): static
    {
        $this->existingTopic = $existingTopic;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;
        return $this;
    }

    public function getNbPosts(): int
    {
        return $this->nbPosts;
    }

    public function setNbPosts(int $nbPosts): static
    {
        $this->nbPosts = $nbPosts;
        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;
        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setRubrique($this);
        }
        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getRubrique() === $this) {
                $post->setRubrique(null);
            }
        }
        return $this;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function setAiSummary(?string $aiSummary): static
    {
        $this->aiSummary = $aiSummary;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }
}
