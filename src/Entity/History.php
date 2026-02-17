<?php

namespace App\Entity;

use App\Repository\HistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Ad;


#[ORM\Entity(repositoryClass: HistoryRepository::class)]
class History
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'histories', targetEntity:User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'histories', targetEntity:Ad::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ad $ad = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $viewedAt = null;

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

    public function getAd(): ?Ad
    {
        return $this->ad;
    }

    public function setAd(?Ad $ad): static
    {
        $this->ad = $ad;

        return $this;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(\DateTimeImmutable $viewedAt): static
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }
}
