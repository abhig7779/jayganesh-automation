@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}Jay Ganesh CRM</title>
    <link rel="icon" href="{{ asset('img/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh bg-canvas font-sans text-ink antialiased']) }}>
    {{ $slot }}
</body>
</html>
