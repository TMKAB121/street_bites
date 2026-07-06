<?php

declare(strict_types=1);

use App\Enums\SocialPlatform;

it('detects the platform from a profile URL host', function (string $url, SocialPlatform $expected): void {
    expect(SocialPlatform::fromUrl($url))->toBe($expected);
})->with([
    'facebook' => ['https://www.facebook.com/streetbites', SocialPlatform::Facebook],
    'facebook short' => ['https://fb.com/streetbites', SocialPlatform::Facebook],
    'facebook mobile subdomain' => ['https://m.facebook.com/streetbites', SocialPlatform::Facebook],
    'instagram' => ['https://instagram.com/streetbites', SocialPlatform::Instagram],
    'tiktok' => ['https://www.tiktok.com/@streetbites', SocialPlatform::TikTok],
    'x' => ['https://x.com/streetbites', SocialPlatform::X],
    'twitter legacy domain' => ['https://twitter.com/streetbites', SocialPlatform::X],
    'youtube' => ['https://www.youtube.com/@streetbites', SocialPlatform::YouTube],
    'youtube short' => ['https://youtu.be/abc123', SocialPlatform::YouTube],
    'snapchat' => ['https://www.snapchat.com/add/streetbites', SocialPlatform::Snapchat],
    'uppercase host' => ['https://WWW.INSTAGRAM.COM/streetbites', SocialPlatform::Instagram],
]);

it('falls back to Website for anything it does not recognise', function (string $url): void {
    expect(SocialPlatform::fromUrl($url))->toBe(SocialPlatform::Website);
})->with([
    'own site' => 'https://streetbites.example.com',
    // A platform name in the path or as a lookalike domain must not match —
    // only the real host does.
    'platform name in path' => 'https://evil.example.com/facebook.com',
    'lookalike domain' => 'https://notfacebook.com/streetbites',
    'not a url' => 'not a url at all',
    'empty' => '',
]);
