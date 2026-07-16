<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Safer Handling Admin') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('assets/safer-handling-logo.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-sh-text">
        <div class="min-h-screen brand-page-bg flex flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md brand-card">
                <div class="brand-header-bar flex-col items-start sm:flex-row sm:items-center">
                    <x-brand-logo class="max-w-[180px]" />
                    <div>
                        <h1 class="text-xl font-semibold leading-tight">Admin Portal</h1>
                        <p class="mt-1 text-sm text-white/95">Manage enquiries, settings, and training courses.</p>
                    </div>
                </div>

                <div class="bg-gradient-to-b from-white to-[#fbfdff] px-6 py-6 sm:px-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
