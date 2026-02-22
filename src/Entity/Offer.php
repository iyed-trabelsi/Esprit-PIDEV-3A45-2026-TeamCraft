<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'offer', targetEntity: Postulation::class, orphanRemoval: true)]
    private Collection $postulations;

    public function __construct()
    {
        $this->postulations = new ArrayCollection();
    }

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(max: 255)]
    #[Assert\Regex('/^[\w\s\-\!\?\#\.]+$/', message: "Le titre contient des caractères non autorisés.")]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poster = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le jeu est obligatoire.")]
    private ?string $game = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le rôle est obligatoire.")]
    private ?string $role = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le rang est obligatoire.")]
    private ?string $rank = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le nombre de joueurs à recruter est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de joueurs doit être positif.")]
    private ?int $nbPlayerRecruited = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getDateExpiration(): ?\DateTimeInterface
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeInterface $dateExpiration): static
    {
        $this->dateExpiration = $dateExpiration;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): static
    {
        $this->poster = $poster;

        return $this;
    }

    public function getGame(): ?string
    {
        return $this->game;
    }

    public function setGame(string $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRank(): ?string
    {
        return $this->rank;
    }

    public function setRank(string $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getNbPlayerRecruited(): ?int
    {
        return $this->nbPlayerRecruited;
    }

    public function setNbPlayerRecruited(int $nbPlayerRecruited): static
    {
        $this->nbPlayerRecruited = $nbPlayerRecruited;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    /**
     * @return Collection<int, Postulation>
     */
    public function getPostulations(): Collection
    {
        return $this->postulations;
    }

    public function addPostulation(Postulation $postulation): static
    {
        if (!$this->postulations->contains($postulation)) {
            $this->postulations->add($postulation);
            $postulation->setOffer($this);
        }

        return $this;
    }

    public function removePostulation(Postulation $postulation): static
    {
        if ($this->postulations->removeElement($postulation)) {
            // set the owning side to null (unless already changed)
            if ($postulation->getOffer() === $this) {
                $postulation->setOffer(null);
            }
        }

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;

        return $this;
    }

    public function incrementViews(): static
    {
        $this->views++;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    #[Assert\Callback]
    public function validateExpirationDate(ExecutionContextInterface $context): void
    {
        if ($this->dateCreation && $this->dateExpiration) {
            if ($this->dateExpiration <= $this->dateCreation) {
                $context->buildViolation("The expiration date must be AFTER the creation date.")
                    ->atPath('dateExpiration')
                    ->addViolation();
            }
        }
    }
}
