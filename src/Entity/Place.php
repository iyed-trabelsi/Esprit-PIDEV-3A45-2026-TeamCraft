<?php

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_place')]
    /** @var int|null */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du lieu est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom du lieu doit contenir au moins {{ limit }} caract??res.',
        maxMessage: 'Le nom du lieu ne peut pas d??passer {{ limit }} caract??res.'
    )]
    private ?string $nomPlace = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type du lieu est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le type du lieu doit contenir au moins {{ limit }} caract??res.'
    )]
    private ?string $typePlace = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L???adresse est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L???adresse doit contenir au moins {{ limit }} caract??res.'
    )]
    private ?string $adresse = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La capacit?? maximale est obligatoire.')]
    #[Assert\Type(type: 'integer', message: 'La capacit?? doit ??tre un nombre entier.')]
    #[Assert\Range(
        min: 1,
        max: 50000,
        notInRangeMessage: 'La capacit?? doit ??tre comprise entre {{ min }} et {{ max }}.'
    )]
    private ?int $capaciteMax = null;

    /** @var Collection<int, Evenement> */
    #[ORM\OneToMany(mappedBy: 'place', targetEntity: Evenement::class)]
    private Collection $evenements;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomPlace(): ?string
    {
        return $this->nomPlace;
    }

    public function setNomPlace(?string $nomPlace): static
    {
        $this->nomPlace = $nomPlace ? trim($nomPlace) : null;
        return $this;
    }

    public function getTypePlace(): ?string
    {
        return $this->typePlace;
    }

    public function setTypePlace(?string $typePlace): static
    {
        $this->typePlace = $typePlace ? trim($typePlace) : null;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse ? trim($adresse) : null;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements[] = $evenement;
            $evenement->setPlace($this);
        }

        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            if ($evenement->getPlace() === $this) {
                $evenement->setPlace(null);
            }
        }

        return $this;
    }

    // ===================== Callback validation =====================
    #[Assert\Callback]
    public function validatePlace(ExecutionContextInterface $context): void
    {
        if ($this->nomPlace !== null && strlen(trim($this->nomPlace)) < 3) {
            $context->buildViolation('Le nom du lieu doit contenir au moins 3 caract??res.')
                ->atPath('nomPlace')
                ->addViolation();
        }

        if ($this->typePlace !== null && strlen(trim($this->typePlace)) < 3) {
            $context->buildViolation('Le type du lieu doit contenir au moins 3 caract??res.')
                ->atPath('typePlace')
                ->addViolation();
        }

        if ($this->adresse !== null && strlen(trim($this->adresse)) < 5) {
            $context->buildViolation('L???adresse doit contenir au moins 5 caract??res.')
                ->atPath('adresse')
                ->addViolation();
        }

        if ($this->capaciteMax !== null) {
            if ($this->capaciteMax < 1) {
                $context->buildViolation('La capacit?? doit ??tre au moins de 1.')
                    ->atPath('capaciteMax')
                    ->addViolation();
            } elseif ($this->capaciteMax > 50000) {
                $context->buildViolation('La capacit?? ne peut pas d??passer 50 000.')
                    ->atPath('capaciteMax')
                    ->addViolation();
            }
        }
    }
}
