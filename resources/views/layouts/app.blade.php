<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CSFloat Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 text-zinc-100 min-h-screen">
<header class="border-b border-zinc-800 bg-zinc-900/60">
    <nav class="mx-auto flex max-w-5xl items-center gap-6 px-6 py-4">
        <span class="font-semibold tracking-tight">CSFloat Tracker</span>
        <a href="{{ route('dashboard') }}"
           class="text-sm {{ request()->routeIs('dashboard') ? 'text-sky-400' : 'text-zinc-400 hover:text-zinc-100' }}">Dashboard</a>
        <a href="{{ route('skins.index') }}"
           class="text-sm {{ request()->routeIs('skins.index') ? 'text-sky-400' : 'text-zinc-400 hover:text-zinc-100' }}">Tracked Skins</a>
        <a href="{{ route('sales.index') }}"
           class="text-sm {{ request()->routeIs('sales.index') ? 'text-sky-400' : 'text-zinc-400 hover:text-zinc-100' }}">Sales History</a>
        <a href="{{ route('analytics.index') }}"
           class="text-sm {{ request()->routeIs('analytics.index') ? 'text-sky-400' : 'text-zinc-400 hover:text-zinc-100' }}">Analytics</a>
    </nav>
</header>

<main class="mx-auto max-w-5xl px-6 py-8 space-y-6">
    @if (session('status'))
        <p class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-5 py-3 text-sm text-red-300">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
