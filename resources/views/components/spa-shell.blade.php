@php
    /*
     * Cangkang SPA. Seluruh UI dirender React dari resources/js/spa.tsx; Laravel
     * hanya menyuntikkan identitas sesi aktif agar React bisa memulihkan state
     * tanpa round-trip tambahan. Tidak ada markup UI Blade di sini.
     */
    $staffUser = auth()->user();
    $customerUser = auth('customer')->user();
    $branding = app(App\Services\SiteSettingsService::class)->current();
    $seo = app(App\Services\SeoService::class)->resolve(request()->path());

    $spaData = [
        'midtransClientKey' => env('MIDTRANS_CLIENT_KEY'),
        'isMidtransProduction' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'user' => $staffUser && $staffUser->role ? [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'role' => $staffUser->role->value,
            'role_label' => $staffUser->role->label(),
            'navigation' => $staffUser->role->navigation(),
            'landing_route' => $staffUser->role->navigation()[0]['route'] ?? 'dashboard',
        ] : null,
        'customer' => $customerUser ? [
            'id' => $customerUser->id,
            'name' => $customerUser->name,
            'email' => $customerUser->email,
            'phone' => $customerUser->phone,
            'is_active' => $customerUser->is_active,
            'balance' => $customerUser->balance,
            'email_verified_at' => $customerUser->email_verified_at?->toIso8601String(),
        ] : null,
        'branding' => [
            'brand_name' => $branding->brand_name ?? 'Rajahemat Cafe',
            'brand_tagline' => $branding->brand_tagline,
            'logo_url' => $branding->logoUrl(),
            'favicon_url' => $branding->faviconUrl(),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">

    @foreach($seo['og'] as $property => $content)
        <meta property="{{ $property }}" content="{{ $content }}">
    @endforeach

    @foreach($seo['twitter'] as $name => $content)
        <meta name="{{ $name }}" content="{{ $content }}">
    @endforeach

    <link rel="icon" type="image/svg+xml" href="{{ $branding->faviconUrl() }}">

    @foreach($seo['jsonLd'] as $schema)
        <script type="application/ld+json">
            {{ json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) }}
        </script>
    @endforeach

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/spa.tsx'])
</head>
<body class="min-h-screen bg-bg text-ink font-sans antialiased">
    <div id="app" data-props="{{ json_encode($spaData) }}"></div>
</body>
</html>