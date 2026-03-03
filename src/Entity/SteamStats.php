<?php

namespace App\Entity;

use App\Repository\SteamStatsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SteamStatsRepository::class)]
class SteamStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @var int|null */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $steamId = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalMatches = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalPlaytime = null;

    #[ORM\Column(nullable: true)]
    private ?int $wins = null;

    #[ORM\Column(nullable: true)]
    private ?int $losses = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $currentRank = null;

    #[ORM\Column(nullable: true)]
    private ?int $kills = null;

    #[ORM\Column(nullable: true)]
    private ?int $deaths = null;

    #[ORM\Column(nullable: true)]
    private ?int $assists = null;

    #[ORM\Column(nullable: true)]
    private ?int $headshots = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUpdated = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSteamId(): ?string
    {
        return $this->steamId;
    }

    public function setSteamId(string $steamId): static
    {
        $this->steamId = $steamId;
        return $this;
    }

    public function getTotalMatches(): ?int
    {
        return $this->totalMatches;
    }

    public function setTotalMatches(?int $totalMatches): static
    {
        $this->totalMatches = $totalMatches;
        return $this;
    }

    public function getTotalPlaytime(): ?int
    {
        return $this->totalPlaytime;
    }

    public function setTotalPlaytime(?int $totalPlaytime): static
    {
        $this->totalPlaytime = $totalPlaytime;
        return $this;
    }

    public function getWins(): ?int
    {
        return $this->wins;
    }

    public function setWins(?int $wins): static
    {
        $this->wins = $wins;
        return $this;
    }

    public function getLosses(): ?int
    {
        return $this->losses;
    }

    public function setLosses(?int $losses): static
    {
        $this->losses = $losses;
        return $this;
    }

    public function getCurrentRank(): ?string
    {
        return $this->currentRank;
    }

    public function setCurrentRank(?string $currentRank): static
    {
        $this->currentRank = $currentRank;
        return $this;
    }

    public function getKills(): ?int
    {
        return $this->kills;
    }

    public function setKills(?int $kills): static
    {
        $this->kills = $kills;
        return $this;
    }

    public function getDeaths(): ?int
    {
        return $this->deaths;
    }

    public function setDeaths(?int $deaths): static
    {
        $this->deaths = $deaths;
        return $this;
    }

    public function getAssists(): ?int
    {
        return $this->assists;
    }

    public function setAssists(?int $assists): static
    {
        $this->assists = $assists;
        return $this;
    }

    public function getHeadshots(): ?int
    {
        return $this->headshots;
    }

    public function setHeadshots(?int $headshots): static
    {
        $this->headshots = $headshots;
        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getKdRatio(): ?float
    {
        if ($this->deaths === 0 || $this->deaths === null) {
            return $this->kills ? (float) $this->kills : null;
        }
        return round($this->kills / $this->deaths, 2);
    }

    public function getWinRate(): ?float
    {
        if ($this->totalMatches === 0 || $this->totalMatches === null) {
            return null;
        }
        return round(($this->wins / $this->totalMatches) * 100, 2);
    }

    public function getHeadshotPercentage(): ?float
    {
        if ($this->kills === 0 || $this->kills === null) {
            return null;
        }
        return round(($this->headshots / $this->kills) * 100, 2);
    }
}
