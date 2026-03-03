<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Evenement;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_participation')]
    /** @var int|null */
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateInscription = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id_evenement')]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id', name: 'user_id')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateInscription(): ?\DateTime
    {
        return $this->dateInscription;
    }

    public function setDateInscription(?\DateTime $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // ===================== Callback validation =====================
    #[Assert\Callback]
    public function validateParticipation(ExecutionContextInterface $context): void
    {
        // dateInscription must not be null
        if ($this->dateInscription === null) {
            $context->buildViolation('La date d\'inscription est obligatoire.')
                ->atPath('dateInscription')
                ->addViolation();
        } elseif ($this->dateInscription > new \DateTime()) {
            $context->buildViolation('La date d\'inscription ne peut pas ??tre dans le futur.')
                ->atPath('dateInscription')
                ->addViolation();
        }

        // evenement must not be null
        if ($this->evenement === null) {
            $context->buildViolation('L\'??v??nement est obligatoire.')
                ->atPath('evenement')
                ->addViolation();
        }

        // user must not be null
        if ($this->user === null) {
            $context->buildViolation('L\'utilisateur est obligatoire.')
                ->atPath('user')
                ->addViolation();
        }

        // user cannot already participate in the same event
        if ($this->evenement !== null && $this->user !== null) {
            foreach ($this->evenement->getParticipations() as $participation) {
                if ($participation->getUser() === $this->user && $participation !== $this) {
                    $context->buildViolation('Cet utilisateur participe d??j?? ?? cet ??v??nement.')
                        ->atPath('user')
                        ->addViolation();
                    break;
                }
            }
        }

        // dateInscription must be before the event's start date
        if ($this->dateInscription !== null && $this->evenement !== null && $this->evenement->getDateDebut() !== null) {
            if ($this->dateInscription > $this->evenement->getDateDebut()) {
                $context->buildViolation('La date d\'inscription ne peut pas ??tre apr??s la date de d??but de l\'??v??nement.')
                    ->atPath('dateInscription')
                    ->addViolation();
            }
        }
    }
}
