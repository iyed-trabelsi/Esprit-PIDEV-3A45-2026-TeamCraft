<?php

namespace App\Entity;

use App\Repository\RiotStatsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RiotStatsRepository::class)]
class RiotStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @var int|null */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 78, unique: true)]
    private ?string $puuid = null;

    #[ORM\Column(length: 255)]
    private ?string $gameName = null;

    #[ORM\Column(length: 255)]
    private ?string $tagLine = null;

    #[ORM\Column(length: 10)]
    private ?string $region = null;

    #[ORM\Column(length: 20)]
    private ?string $game = null; // 'lol' or 'valorant'

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rank = null; // Full rank: "GOLD IV"

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tier = null; // e.g., "GOLD"

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $division = null; // e.g., "IV"

    #[ORM\Column(nullable: true)]
    private ?int $leaguePoints = null;

    #[ORM\Column(nullable: true)]
    private ?int $wins = null;

    #[ORM\Column(nullable: true)]
    private ?int $losses = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUpdated = null;

    /** @var array<int, mixed> */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $recentMatches = [];

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

    public function getPuuid(): ?string
    {
        return $this->puuid;
    }

    public function setPuuid(string $puuid): static
    {
        $this->puuid = $puuid;
        return $this;
    }

    public function getGameName(): ?string
    {
        return $this->gameName;
    }

    public function setGameName(string $gameName): static
    {
        $this->gameName = $gameName;
        return $this;
    }

    public function getTagLine(): ?string
    {
        return $this->tagLine;
    }

    public function setTagLine(string $tagLine): static
    {
        $this->tagLine = $tagLine;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): static
    {
        $this->region = $region;
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

    public function getRank(): ?string
    {
        return $this->rank;
    }

    public function setRank(?string $rank): static
    {
        $this->rank = $rank;
        return $this;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(?string $tier): static
    {
        $this->tier = $tier;
        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(?string $division): static
    {
        $this->division = $division;
        return $this;
    }

    public function getLeaguePoints(): ?int
    {
        return $this->leaguePoints;
    }

    public function setLeaguePoints(?int $leaguePoints): static
    {
        $this->leaguePoints = $leaguePoints;
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

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRecentMatches(): array
    {
        return $this->recentMatches;
    }

    /**
     * @param array<int, mixed> $recentMatches
     */
    public function setRecentMatches(array $recentMatches): static
    {
        $this->recentMatches = $recentMatches;
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

    /**
     * Calculate win rate percentage
     */
    public function getWinRate(): ?float
    {
        $totalGames = $this->getTotalGames();
        if ($totalGames === 0) {
            return null;
        }
        return round(($this->wins / $totalGames) * 100, 2);
    }

    /**
     * Get total games played
     */
    public function getTotalGames(): int
    {
        return ($this->wins ?? 0) + ($this->losses ?? 0);
    }

    /**
     * Get full Riot ID (GameName#TagLine)
     */
    public function getRiotId(): string
    {
        return $this->gameName . '#' . $this->tagLine;
    }
}
