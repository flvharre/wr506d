<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\MovieRepository;
use App\State\MovieCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ApiFilter(BooleanFilter::class, properties: ['online'])]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'director.id' => 'exact',
    'categories.id' => 'exact',
    'createdBy.id' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['releaseDate'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['movie:read']],
            security: "is_granted('PUBLIC_ACCESS')"
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['movie:list']],
            security: "is_granted('PUBLIC_ACCESS')"
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: MovieCreateProcessor::class,
            input: \App\Dto\MovieInput::class // fortement recommandé pour l'upload
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or object.getCreatedBy() == user",
            securityMessage: "Seul l'administrateur ou l'auteur du film peut le modifier."
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or object.getCreatedBy() == user",
            securityMessage: "Seul l'administrateur ou l'auteur du film peut le supprimer."
        ),
    ],
    paginationItemsPerPage: 10
)]
class Movie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['movie:list', 'movie:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du film est obligatoire.")]
    #[Assert\Length(min: 2, minMessage: "Le nom doit contenir au moins 2 caractères.")]
    #[Groups(['movie:list', 'movie:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['movie:list', 'movie:read'])]
    private ?string $description = null;

    #[ORM\Column(name: 'released', type: Types::DATETIME_MUTABLE)]
    #[Groups(['movie:list', 'movie:read'])]
    private ?\DateTimeInterface $releaseDate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['movie:list', 'movie:read'])]
    private ?int $duration = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['movie:list', 'movie:read'])]
    private ?float $metascore = null;

    #[ORM\Column]
    #[Groups(['movie:list', 'movie:read'])]
    private ?bool $online = false;

    #[ORM\Column]
    #[Groups(['movie:list', 'movie:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'movies')]
    #[Groups(['movie:list', 'movie:read'])]
    private ?Director $director = null;

    #[ORM\ManyToOne(inversedBy: 'movies')]
    #[Groups(['movie:list', 'movie:read'])]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'movies')]
    #[Groups(['movie:list', 'movie:read'])]
    private Collection $categories;

    /**
     * @var Collection<int, Actor>
     */
    #[ORM\ManyToMany(targetEntity: Actor::class, mappedBy: 'movies')]
    #[Groups(['movie:read'])]
    private Collection $actors;

    /**
     * UNE SEULE AFFICHE PAR FILM → OneToOne
     */
    #[ORM\OneToOne(mappedBy: 'movie', targetEntity: MediaObject::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['movie:read', 'movie:write'])]
    private ?MediaObject $poster = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'movie', orphanRemoval: true)]
    #[Groups(['movie:read'])]
    private Collection $comments;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->actors = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->online = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getMetascore(): ?float
    {
        return $this->metascore;
    }

    public function setMetascore(?float $metascore): static
    {
        $this->metascore = $metascore;
        return $this;
    }

    public function isOnline(): ?bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): static
    {
        $this->online = $online;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getDirector(): ?Director
    {
        return $this->director;
    }

    public function setDirector(?Director $director): static
    {
        $this->director = $director;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    // === GESTION DE L'AFFICHE (NOUVEAU) ===

    public function getPoster(): ?MediaObject
    {
        return $this->poster;
    }

    public function setPoster(?MediaObject $poster): static
    {
        // Si un ancien poster existe, on le dissocie
        if ($this->poster && $this->poster !== $poster) {
            $this->poster->setMovie(null);
        }

        $this->poster = $poster;

        if ($poster) {
            $poster->setMovie($this);
        }

        return $this;
    }

    // === CATEGORIES ===
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addMovie($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeMovie($this);
        }
        return $this;
    }

    // === ACTEURS ===
    public function getActors(): Collection
    {
        return $this->actors;
    }

    public function addActor(Actor $actor): static
    {
        if (!$this->actors->contains($actor)) {
            $this->actors->add($actor);
            $actor->addMovie($this);
        }
        return $this;
    }

    public function removeActor(Actor $actor): static
    {
        if ($this->actors->removeElement($actor)) {
            $actor->removeMovie($this);
        }
        return $this;
    }

    // === COMMENTAIRES ===
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setMovie($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            $comment->setMovie(null);
        }
        return $this;
    }
}
