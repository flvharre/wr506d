<?php

namespace App\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final class MultipartDecoder implements DecoderInterface
{
    public const FORMAT = 'multipart';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        // ✅ Correction : on ne décode que si c’est bien du JSON
        $decoded = [];
        foreach ($request->request->all() as $key => $value) {
            $decoded[$key] = $this->tryJsonDecode($value);
        }

        return $decoded + $request->files->all();
    }

    private function tryJsonDecode(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // ce n'est pas du JSON, on renvoie la valeur brute
            return $value;
        }
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}
