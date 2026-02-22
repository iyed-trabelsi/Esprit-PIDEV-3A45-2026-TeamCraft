<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'playerProfile')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $game = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gameRank = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $selectedGames = [];

    #[ORM\Column(nullable: true)]
    private ?int $experienceYears = null;

    #[ORM\Column(nullable: true)]
    private ?float $winrate = null;

    #[ORM\OneToMany(targetEntity: CompetitiveRank::class, mappedBy: 'player', orphanRemoval: true)]
    private Collection $competitiveRanks;

    public function __construct()
    {
        $this->selectedGames = [];
        $this->competitiveRanks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGame(): ?string
    {
        return $this->game;
    }

    public function setGame(?string $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getGameRank(): ?string
    {
        return $this->gameRank;
    }

    public function setGameRank(?string $gameRank): static
    {
        $this->gameRank = $gameRank;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSelectedGames(): array
    {
        return $this->selectedGames ?? [];
    }

    public function setSelectedGames(?array $selectedGames): static
    {
        $this->selectedGames = $selectedGames ?? [];

        return $this;
    }

    public function getExperienceYears(): ?int
    {
        return $this->experienceYears;
    }

    public function setExperienceYears(?int $experienceYears): static
    {
        $this->experienceYears = $experienceYears;

        return $this;
    }

    public function getWinrate(): ?float
    {
        return $this->winrate;
    }

    public function setWinrate(?float $winrate): static
    {
        $this->winrate = $winrate;

        return $this;
    }

    /**
     * @return Collection<int, CompetitiveRank>
     */
    public function getCompetitiveRanks(): Collection
    {
        return $this->competitiveRanks;
    }

    public function addCompetitiveRank(CompetitiveRank $competitiveRank): static
    {
        if (!$this->competitiveRanks->contains($competitiveRank)) {
            $this->competitiveRanks->add($competitiveRank);
            $competitiveRank->setPlayer($this);
        }

        return $this;
    }

    public function removeCompetitiveRank(CompetitiveRank $competitiveRank): static
    {
        if ($this->competitiveRanks->removeElement($competitiveRank)) {
            // set the owning side to null (unless already changed)
            if ($competitiveRank->getPlayer() === $this) {
                $competitiveRank->setPlayer(null);
            }
        }

        return $this;
    }
}
