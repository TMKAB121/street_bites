{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- Rendered by the sitemap route (routes/web.php). Each entry is a
     ['loc' => …, 'lastmod' => ?string] pair — the route decides what pages
     exist; this view only knows how to print urlset XML. Blade's {{ }}
     escaping is XML-safe (& → &amp;). --}}
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
