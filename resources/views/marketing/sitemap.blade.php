{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ([route('home'), route('about'), route('contact'), ...collect($pages)->keys()->map(fn ($slug) => route('solutions.'.$slug))->all()] as $url)
        <url>
            <loc>{{ $url }}</loc>
            <lastmod>{{ now()->toDateString() }}</lastmod>
        </url>
    @endforeach
</urlset>
