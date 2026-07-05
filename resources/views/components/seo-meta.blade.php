{{--
    The shared <head> metadata block: <title>, meta description, canonical, and
    the Open Graph / Twitter card tags that control link previews. Rendered once
    per full page (both layouts render it from their $title/$description/$robots
    props; welcome and styleguide call it directly).

    The default share image is public/images/og-image.jpg — the 1200x630 (1.91:1)
    branded card, kept under 300 KB so every crawler (WhatsApp is the strictest)
    caches it. og:image must be an absolute URL, so overrides should pass url(...).
    The width/height/alt tags are only emitted for the default image because an
    override's dimensions aren't known here.

    robots: pass "noindex" for pages crawlers shouldn't index (auth steps, the
    styleguide, internal search results). Canonical is skipped on those pages —
    a canonical on a noindex page sends crawlers mixed signals.

    Twitter/X falls back to og:title / og:description / og:image, so only
    twitter:card is emitted — don't add redundant twitter:* duplicates.
--}}
@props([
    'title' => 'Street Bites — Find food trucks near you',
    'description' => null,
    'image' => null,
    'type' => 'website',
    'robots' => null,
])

@php
    $description ??= 'Crave it? Find it. Instantly. Street Bites maps the food '
        .'trucks rolling through your city — live locations, menus, cuisines, '
        .'and open-right-now hours.';
    $isDefaultImage = $image === null;
    $image ??= url('/images/og-image.jpg');
    $url = url()->current();
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
@if ($robots)
    <meta name="robots" content="{{ $robots }}">
@else
    <link rel="canonical" href="{{ $url }}">
@endif

<meta property="og:site_name" content="Street Bites">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $url }}">
<meta property="og:image" content="{{ $image }}">
@if ($isDefaultImage)
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Street Bites — Crave it? Find it. Instantly.">
@endif
<meta name="twitter:card" content="summary_large_image">
