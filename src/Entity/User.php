<?php

namespace App\Entity;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserRepository;
use App\State\UserPasswordHasher;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_API_KEY_HASH', fields: ['apiKeyHash'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: "Ce nom d'utilisateur est déjà pris.")]
#[UniqueEntity(fields: ['apiKeyHash'], message: 'Cette clé API existe déjà.')]
#[ApiResource(
    operations: [
        new Post(
            security: "is_granted('PUBLIC_ACCESS')",
            processor: UserPasswordHasher::class
        ),
        new Get(
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('ROLE_ADMIN') or object == user"
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['user:list']],
            security: "is_granted('PUBLIC_ACCESS')"
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or object == user",
            processor: UserPasswordHasher::class
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'user:list', 'comment:read', 'comment:list', 'movie:read', 'movie:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères.")]
    #[Groups(['user:read', 'user:write', 'user:list'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom d'utilisateur est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le nom d'utilisateur doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le nom d'utilisateur ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_-]+$/',
        message: "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores."
    )]
    #[Groups(['user:read', 'user:write', 'user:list', 'comment:read', 'comment:list', 'movie:read', 'movie:list'])]
    private ?string $username = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:write', 'user:list'])]
    private array $roles = ['ROLE_AUTHOR'];

    #[ORM\Column]
    private ?string $password = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.", groups: ['create'])]
    #[Assert\Length(
        min: 4,
        max: 255,
        minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le mot de passe ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:list'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 100])]
    #[Assert\Positive(message: "La limite d'API doit être un nombre positif.")]
    #[Assert\LessThanOrEqual(value: 10000, message: "La limite d'API ne peut pas dépasser {{ compared_value }}.")]
    #[Groups(['user:read', 'user:write', 'user:list'])]
    private int $apiRateLimit = 10000;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Assert\Length(exactly: 64, exactMessage: "Le hash de la clé API doit faire exactement {{ limit }} caractères.")]
    private ?string $apiKeyHash = null;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    #[Assert\Length(exactly: 16, exactMessage: "Le préfixe de la clé API doit faire exactement {{ limit }} caractères.")]
    #[Groups(['user:read'])]
    private ?string $apiKeyPrefix = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:write'])]
    private bool $apiKeyEnabled = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?DateTimeImmutable $apiKeyCreatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?DateTimeImmutable $apiKeyLastUsedAt = null;

    // Champs 2FA
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $twoFactorBackupCodes = null;

    /**
     * @var Collection<int, Movie>
     */
    #[ORM\OneToMany(targetEntity: Movie::class, mappedBy: 'createdBy', cascade: ['remove'], orphanRemoval: true)]
    private Collection $movies;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', cascade: ['remove'], orphanRemoval: true)]
    private Collection $comments;

    public function __construct()
    {
        $this->movies = new ArrayCollection();
        $this->comments = new ArrayCollection();
        if (empty($this->roles)) {
            $this->roles = ['ROLE_AUTHOR'];
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (empty($roles)) {
            $roles[] = 'ROLE_AUTHOR';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        if (empty($roles)) {
            $this->roles = ['ROLE_AUTHOR'];
        } else {
            $this->roles = $roles;
        }
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getApiRateLimit(): int
    {
        return $this->apiRateLimit;
    }

    public function setApiRateLimit(int $apiRateLimit): static
    {
        $this->apiRateLimit = $apiRateLimit;
        return $this;
    }

    public function getApiKeyHash(): ?string
    {
        return $this->apiKeyHash;
    }

    public function setApiKeyHash(?string $apiKeyHash): static
    {
        $this->apiKeyHash = $apiKeyHash;
        return $this;
    }

    public function getApiKeyPrefix(): ?string
    {
        return $this->apiKeyPrefix;
    }

    public function setApiKeyPrefix(?string $apiKeyPrefix): static
    {
        $this->apiKeyPrefix = $apiKeyPrefix;
        return $this;
    }

    public function isApiKeyEnabled(): bool
    {
        return $this->apiKeyEnabled;
    }

    public function setApiKeyEnabled(bool $apiKeyEnabled): static
    {
        $this->apiKeyEnabled = $apiKeyEnabled;
        return $this;
    }

    public function getApiKeyCreatedAt(): ?DateTimeImmutable
    {
        return $this->apiKeyCreatedAt;
    }

    public function setApiKeyCreatedAt(?DateTimeImmutable $apiKeyCreatedAt): static
    {
        $this->apiKeyCreatedAt = $apiKeyCreatedAt;
        return $this;
    }

    public function getApiKeyLastUsedAt(): ?DateTimeImmutable
    {
        return $this->apiKeyLastUsedAt;
    }

    public function setApiKeyLastUsedAt(?DateTimeImmutable $apiKeyLastUsedAt): static
    {
        $this->apiKeyLastUsedAt = $apiKeyLastUsedAt;
        return $this;
    }

    public function updateApiKeyLastUsedAt(): void
    {
        $this->apiKeyLastUsedAt = new DateTimeImmutable();
    }

    // Getters et Setters 2FA
    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(?string $twoFactorSecret): static
    {
        $this->twoFactorSecret = $twoFactorSecret;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function getTwoFactorBackupCodes(): ?array
    {
        return $this->twoFactorBackupCodes;
    }

    public function setTwoFactorBackupCodes(?array $twoFactorBackupCodes): static
    {
        $this->twoFactorBackupCodes = $twoFactorBackupCodes;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new DateTimeImmutable();
        }
        if (empty($this->roles)) {
            $this->roles = ['ROLE_AUTHOR'];
        }
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password ?? '');
        return $data;
    }

    /**
     * @return Collection<int, Movie>
     */
    public function getMovies(): Collection
    {
        return $this->movies;
    }

    public function addMovie(Movie $movie): static
    {
        if (!$this->movies->contains($movie)) {
            $this->movies->add($movie);
            $movie->setCreatedBy($this);
        }
        return $this;
    }

    public function removeMovie(Movie $movie): static
    {
        if ($this->movies->removeElement($movie)) {
            if ($movie->getCreatedBy() === $this) {
                $movie->setCreatedBy(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getAuthor() === $this) {
                $comment->setAuthor(null);
            }
        }
        return $this;
    }
}
