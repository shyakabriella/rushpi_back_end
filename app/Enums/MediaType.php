<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case IMAGE = 'image';

    /**
     * Return a readable media type.
     */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
        };
    }

    /**
     * Return the MIME types allowed for this media type.
     *
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::IMAGE => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        };
    }

    /**
     * Return the maximum allowed file size in kilobytes.
     */
    public function maximumSizeInKilobytes(): int
    {
        return match ($this) {
            self::IMAGE => 5120,
        };
    }

    /**
     * Determine whether the uploaded MIME type is allowed.
     */
    public function acceptsMimeType(string $mimeType): bool
    {
        return in_array(
            $mimeType,
            $this->allowedMimeTypes(),
            true
        );
    }

    /**
     * Return all media type values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
