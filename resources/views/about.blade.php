{{--
    Public "About us" page (route: about), linked from the hamburger menu and
    the desktop header nav. Static content on the shared shell chrome: the
    mission (with the founding question as a pull-quote), the developer intro,
    and the follow-along link cards. Bespoke visuals live in
    resources/css/components/about.css; headings/rhythm are Tailwind utilities.
--}}
@php
    // Follow-along destinations, rendered as tappable cards.
    $connections = [
        [
            'title' => 'Watch the build',
            'text' => 'I am documenting the entire development process of this application in a multi-day build vlog. Check out the behind-the-scenes engineering on the Sayge Dev YouTube playlist.',
            'cta' => 'Sayge Dev on YouTube',
            'href' => 'https://www.youtube.com/playlist?list=PLCFAvrjCdis-mdDgzj3wAYA6wXjzgml9z',
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z"/>',
        ],
        [
            'title' => 'See the code',
            'text' => 'Want to dig into the architecture, the application setup, and the responsive design system? Explore the source code over on my GitHub.',
            'cta' => 'Street Bites on GitHub',
            'href' => 'https://github.com/TMKAB121/vlog_street_bites',
            'icon' => '<path d="m8 7-5 5 5 5"/><path d="m16 7 5 5-5 5"/>',
        ],
        [
            'title' => 'Professional network',
            'text' => 'Let\'s talk shop, development workflows, or technology leadership. Connect with me on LinkedIn.',
            'cta' => 'Connect on LinkedIn',
            'href' => 'https://www.linkedin.com/in/tony-sayge-6a181992/',
            'icon' => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>',
        ],
    ];

    // Optional Buy Me a Coffee support card — only when the URL is configured.
    if ($coffee = config('external-links.buymeacoffee')) {
        $connections[] = [
            'title' => 'Support the project',
            'text' => 'Street Bites is free to use. If it helps you find your next meal, buy me a coffee to help fund hosting and new features.',
            'cta' => 'Buy me a coffee',
            'href' => $coffee,
            'icon' => '<path d="M4 8h13v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2"/><path d="M7 3v2"/><path d="M11 3v2"/><path d="M15 3v2"/>',
        ];
    }
@endphp

<x-layouts::shell
    title="About us — Street Bites"
    description="The story behind Street Bites — one developer's mission to connect hungry locals with the food trucks rolling through their city."
    active="about"
>
    <header class="mb-8">
        <h1 class="text-xl font-semibold text-primary">About Street Bites</h1>
        <p class="text-text-muted mt-1">The mission, the developer, and how to follow the build.</p>
    </header>

    <section class="mb-8">
        <h2 class="text-lg font-semibold text-primary mb-3">The mission behind Street Bites</h2>

        <blockquote class="about-page__quote">
            &ldquo;Where is that food truck right now, and what&rsquo;s on the menu today?&rdquo;
        </blockquote>

        <div class="about-page__prose">
            <p>Street Bites was born out of that simple, everyday frustration.</p>
            <p>
                As a mobile-first, responsive web application, Street Bites aims to bridge
                the gap between hungry locals and community food trucks. Food trucks are
                inherently dynamic, moving from neighborhood squares to evening festivals,
                which makes tracking them down half the battle. Street Bites provides a
                clean, fast, and real-time solution to track your favorite local eats, view
                updated menus, and never miss a food truck pop-up again.
            </p>
            <p>
                Whether you&rsquo;re craving gourmet street tacos, artisanal sliders, or a
                local dessert truck, Street Bites puts the local food truck scene right at
                your fingertips.
            </p>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-semibold text-primary mb-3">About the developer</h2>

        <p class="about-page__lead">Hi, I&rsquo;m Tony Sayge.</p>

        <div class="about-page__prose">
            <p>
                By day, I am an Associate Director of Technology and Lead Developer,
                managing complex digital architectures and building robust enterprise web
                applications. By night, I&rsquo;m a passionate creator who loves diving deep
                into modern application setups, mobile-first design, and sleek,
                user-centric code workflows.
            </p>
            <p>
                Street Bites is a passion project built to explore clean, scalable
                development architectures&mdash;solving a real-world problem while refining
                high-performance web solutions. I love documenting the process of bringing
                an idea from a blank text editor to a fully deployed application, sharing
                the triumphs, the bugs, and the architectural decisions along the way.
            </p>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-semibold text-primary mb-3">Follow the journey &amp; connect</h2>

        <p class="about-page__prose">
            If you&rsquo;re a fellow developer, a local foodie, or just curious about how
            modern web apps are built from scratch, I&rsquo;d love to connect and share the
            process with you.
        </p>

        <div class="about-page__cards">
            @foreach ($connections as $connection)
                <a
                    href="{{ $connection['href'] }}"
                    class="about-page__card"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <span class="about-page__card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">{!! $connection['icon'] !!}</svg>
                    </span>
                    <span class="about-page__card-body">
                        <span class="about-page__card-title">{{ $connection['title'] }}</span>
                        <span class="about-page__card-text">{{ $connection['text'] }}</span>
                        <span class="about-page__card-cta">{{ $connection['cta'] }} &rarr;</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
</x-layouts::shell>
