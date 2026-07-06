<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The social platforms we recognise on a truck's profile links. The vendor
 * never picks one — fromUrl() detects it from the pasted link's host, and the
 * value drives which brand icon <x-social-links> renders. Anything we don't
 * recognise falls back to Website (a generic globe), so an unknown platform
 * still gets a working link rather than an error.
 */
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case X = 'x';
    case YouTube = 'youtube';
    case Snapchat = 'snapchat';
    case Website = 'website';

    /**
     * Detect the platform from a profile URL by its host: an exact domain
     * match or any subdomain of it (m.facebook.com, www.tiktok.com, …).
     */
    public static function fromUrl(string $url): self
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return self::Website;
        }

        $host = mb_strtolower($host);

        foreach (self::domains() as $platform => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return self::from($platform);
                }
            }
        }

        return self::Website;
    }

    /** Human label — link text for screen readers and hover titles. */
    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::X => 'X (Twitter)',
            self::YouTube => 'YouTube',
            self::Snapchat => 'Snapchat',
            self::Website => 'Website',
        };
    }

    /**
     * The bare domains each platform is reachable on (Meta's products appear
     * as their consumer brands — facebook.com/fb.com and instagram.com).
     *
     * @return array<string, list<string>>
     */
    private static function domains(): array
    {
        return [
            self::Facebook->value => ['facebook.com', 'fb.com', 'fb.me'],
            self::Instagram->value => ['instagram.com', 'instagr.am'],
            self::TikTok->value => ['tiktok.com'],
            self::X->value => ['x.com', 'twitter.com'],
            self::YouTube->value => ['youtube.com', 'youtu.be'],
            self::Snapchat->value => ['snapchat.com'],
        ];
    }
}
