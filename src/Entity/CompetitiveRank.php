<?php

namespace App\Entity;

use App\Repository\CompetitiveRankRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitiveRankRepository::class)]
class CompetitiveRank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @var int|null */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Player::class, inversedBy: 'competitiveRanks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column(length: 50)]
    private ?string $game = null;

    #[ORM\Column(length: 100)]
    private ?string $principalRole = null;

    #[ORM\Column(length: 100)]
    private ?string $skillLevel = null;

    #[ORM\Column(length: 100)]
    private ?string $experience = null;

    #[ORM\Column(length: 100)]
    private ?string $availability = null;

    #[ORM\Column(length: 50)]
    private ?string $region = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

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

    public function getPrincipalRole(): ?string
    {
        return $this->principalRole;
    }

    public function setPrincipalRole(string $principalRole): static
    {
        $this->principalRole = $principalRole;

        return $this;
    }

    public function getSkillLevel(): ?string
    {
        return $this->skillLevel;
    }

    public function setSkillLevel(string $skillLevel): static
    {
        $this->skillLevel = $skillLevel;

        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(string $availability): static
    {
        $this->availability = $availability;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
