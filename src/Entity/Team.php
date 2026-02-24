<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Ce nom d\'équipe est déjà utilisé.')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de l'équipe est obligatoire.")]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: 'json')]
    #[Assert\Count(min: 1, minMessage: "Vous devez sélectionner au moins un jeu.")]
    private array $games = [];

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'team_members')]
    private Collection $members;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'team_co_owners')]
    private Collection $coOwners;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getGames(): array
    {
        return $this->games;
    }

    public function setGames(array $games): static
    {
        $this->games = $games;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: Offer::class, orphanRemoval: true)]
    private \Doctrine\Common\Collections\Collection $offers;

    #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMessage::class, orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->offers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->offers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->members = new \Doctrine\Common\Collections\ArrayCollection();
        $this->coOwners = new \Doctrine\Common\Collections\ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }

        return $this;
    }

    public function removeMember(User $member): static
    {
        $this->members->removeElement($member);

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getCoOwners(): Collection
    {
        return $this->coOwners;
    }

    public function addCoOwner(User $coOwner): static
    {
        if (!$this->coOwners->contains($coOwner)) {
            $this->coOwners->add($coOwner);
        }

        return $this;
    }

    public function removeCoOwner(User $coOwner): static
    {
        $this->coOwners->removeElement($coOwner);

        return $this;
    }

    public function isCoOwner(User $user): bool
    {
        return $this->coOwners->contains($user);
    }

    public function hasManagementAccess(User $user): bool
    {
        return $this->owner === $user || $this->isCoOwner($user);
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Offer>
     */
    public function getOffers(): \Doctrine\Common\Collections\Collection
    {
        return $this->offers;
    }

    public function addOffer(Offer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setTeam($this);
        }

        return $this;
    }

    public function removeOffer(Offer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            // set the owning side to null (unless already changed)
            if ($offer->getTeam() === $this) {
                $offer->setTeam(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeamMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(TeamMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTeam($this);
        }

        return $this;
    }

    public function removeMessage(TeamMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getTeam() === $this) {
                $message->setTeam(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
