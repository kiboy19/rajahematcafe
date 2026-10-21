<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($staticPages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
@if($page['lastmod'])
        <lastmod>{{ $page['lastmod'] }}</lastmod>
@endif
        <changefreq>weekly</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
@endforeach
@foreach($posts as $post)
    <url>
        <loc>{{ route('blog.show', ['slug' => $post->slug]) }}</loc>
@if($post->updated_at)
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
@endif
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
</urlset>