<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>{{ $title ?? config('app.name') }}</title>

  @vite (['resources/css/blatui-livewire.css', 'resources/js/blatui/livewire.ts'])

  @livewireStyles
</head>
<body class="min-h-svh bg-background font-sans text-foreground antialiased">
  <main>{{ $slot }}</main>

  @livewireScripts
</body>
</html>
