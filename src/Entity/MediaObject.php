<?php
// api/src/Entity/MediaObject.php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\Movie;
use App\Entity\Actor;

#[Vich\Uploadable]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    types: ['https://schema.org/MediaObject'],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ],
                                    'movie' => [
                                        'type' => 'string',
                                        'format' => 'uri'
                                    ],
                                    'actor' => [
                                        'type' => 'string',
                                        'format' => 'uri'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            )
        )
    ],
    outputFormats: ['jsonld' => ['application/ld+json']],
    normalizationContext: ['groups' => ['media_object:read']]
)]
class MediaObject
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    #[Groups(['media_object:read', 'movie:list', 'movie:read'])]
    private ?int $id = null;

    #[ApiProperty(types: ['https://schema.org/contentUrl'], writable: false)]
    #[Groups(['media_object:read', 'movie:list', 'movie:read'])]
    public ?string $contentUrl = null;

    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePath')]
    #[Assert\NotNull(message: 'Le fichier est obligatoire.')]
    public ?File $file = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(nullable: true)]
    #[Groups(['media_object:read', 'movie:list', 'movie:read'])]
    public ?string $filePath = null;

    #[ORM\ManyToOne(targetEntity: Movie::class, inversedBy: 'mediaObjects')]
    public ?Movie $movie = null;

    #[ORM\ManyToOne(targetEntity: Actor::class, inversedBy: 'mediaObjects')]
    public ?Actor $actor = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): static
    {
        $this->movie = $movie;
        return $this;
    }

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(?Actor $actor): static
    {
        $this->actor = $actor;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}
