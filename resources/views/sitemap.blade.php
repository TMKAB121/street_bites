{{-- Rendered by the sitemap route (routes/web.php), which prepends the XML
     declaration in plain PHP — deliberately NOT here. A literal prolog in a
     Blade template compiles to a cached PHP file that servers with
     short_open_tag=On mis-parse as a PHP open tag (a production 500), so this
     view only ever prints the <urlset> body. Each entry is a
     ['loc' => …, 'lastmod' => ?string] pair; Blade's {{ }} escaping is
     XML-safe (& → &amp;). --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if (($url['lastmod'] ?? null) !== null)
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
