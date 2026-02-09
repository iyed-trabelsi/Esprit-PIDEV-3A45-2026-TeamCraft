<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Place;
use App\Entity\Participation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_evenement')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'événement est obligatoire.')]
    #[Assert\Length(
        min: 8,
        max: 255,
        minMessage: 'Le nom de l\'événement doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nomEvenement = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type d\'événement est obligatoire.')]
    #[Assert\Length(
        min: 8,
        max: 255,
        minMessage: 'Le type d\'événement doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $typeEvenement = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['open', 'closed', 'over'],
        message: 'Le statut doit être "open", "closed" ou "over".'
    )]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Place::class, inversedBy: 'evenements')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id_place')]
    #[Assert\NotNull(message: 'Le lieu est obligatoire.')]
    private ?Place $place = null;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Participation::class, orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomEvenement(): ?string
    {
        return $this->nomEvenement;
    }

    public function setNomEvenement(?string $nomEvenement): static
    {
        $this->nomEvenement = $nomEvenement ? trim($nomEvenement) : null;
        return $this;
    }

    public function getTypeEvenement(): ?string
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(?string $typeEvenement): static
    {
        $this->typeEvenement = $typeEvenement ? trim($typeEvenement) : null;
        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status ? trim($status) : null;
        return $this;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;
        return $this;
    }

    /**
     * @return Collection|Participation[]
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations[] = $participation;
            $participation->setEvenement($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvenement() === $this) {
                $participation->setEvenement(null);
            }
        }

        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organisateur = null;

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        $today = new \DateTime('today');

        // check dates are not null
        if ($this->dateDebut === null) {
            $context->buildViolation('La date de début est obligatoire.')
                ->atPath('dateDebut')
                ->addViolation();
        }

        if ($this->dateFin === null) {
            $context->buildViolation('La date de fin est obligatoire.')
                ->atPath('dateFin')
                ->addViolation();
        }

        if ($this->dateDebut !== null && $this->dateFin !== null) {
            // end date ≥ start date
            if ($this->dateFin < $this->dateDebut) {
                $context->buildViolation('La date de fin doit être postérieure ou égale à la date de début.')
                    ->atPath('dateFin')
                    ->addViolation();
            }

            // start date ≥ today
            if ($this->dateDebut < $today) {
                $context->buildViolation('La date de début ne peut pas être dans le passé.')
                    ->atPath('dateDebut')
                    ->addViolation();
            }
        }
    }

    public function getOrganisateur(): ?User
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?User $organisateur): static
    {
        $this->organisateur = $organisateur;

        return $this;
    }
}
