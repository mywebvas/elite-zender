@props([
    'title' => config('platform.name').' — Multi-Tenant Email Marketing Platform',
    'description' => 'Run email campaigns through your own pool of SMTP relays. Health-weighted rotation, deliverability tooling, automations and real-time analytics.',
    'image' => '/icons/icon-512.png',
    'type' => 'website',
    'noindex' => false,
    'schema' => null,
])

{{--
    Canonical SEO / social block.

    The landing page previously shipped a <title> and a description and nothing
    else: no canonical URL, no Open Graph, no Twitter card, no structured data.
    A link shared on Slack, LinkedIn or X rendered as a bare URL, and search
    engines had nothing to build a rich result from.
--}}

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ url()->current() }}">

@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
@endif

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ config('platform.name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ url($image) }}">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ url($image) }}">

{{-- Icons + PWA --}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/icons/icon-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#4f46e5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

@if ($schema)
    <script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
