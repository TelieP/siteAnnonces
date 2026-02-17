<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?User $recipient = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?Ad $ad = null;

    #[ORM\ManyToOne(inversedBy: 'receivedMessages')]
    private ?User $recipient_user = null;

    #[ORM\Column]
    private ?bool $isRead = false;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageImage::class, cascade: ['persist', 'remove'])]
    private Collection $images;

    public function __construct() {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

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

    public function getRecipientUser(): ?User
    {
        return $this->recipient_user;
    }

    public function setRecipientUser(?User $recipient_user): static
    {
        $this->recipient_user = $recipient_user;

        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

/**
     * @return Collection<int, MessageImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(MessageImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setMessage($this);
        }
        return $this;
    }

    public function removeImage(MessageImage $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getMessage() === $this) {
                $image->setMessage(null);
            }
        }
        return $this;
    }

}
