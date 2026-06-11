<!doctype html>
<html lang="en" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'A/B Testing Dashboard' }}</title>

    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gray-950 text-gray-100">

<div class="flex min-h-screen" x-data="{ sidebarOpen: false }">

    {{-- Mobile sidebar overlay --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60 lg:hidden"
         @click="sidebarOpen = false">
    </div>

    @livewire('ab-testing::dashboard-sidebar')

    {{-- Main column --}}
    <div class="flex flex-1 flex-col min-w-0 lg:pl-0">

        <x-ab-testing::navbar />

        <main class="flex-1 px-6 py-8 lg:px-10">
            {{ $slot }}
        </main>
    </div>
</div>

@livewireScripts

</body>
</html>
