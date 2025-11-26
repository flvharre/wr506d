<?php
// src/Serializer/MediaObjectNormalizer.php

namespace App\Serializer;

use App\Entity\MediaObject;
use Vich\UploaderBundle\Storage\StorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MediaObjectNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'MEDIA_OBJECT_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer,
        private readonly StorageInterface $storage
    ) {
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|\ArrayObject|string|int|float|bool|null {
        $context[self::ALREADY_CALLED] = true;

        if ($data instanceof MediaObject) {
            $data->contentUrl = $this->storage->resolveUri($data, 'file');
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): bool {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof MediaObject;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MediaObject::class => true,
        ];
    }
}
