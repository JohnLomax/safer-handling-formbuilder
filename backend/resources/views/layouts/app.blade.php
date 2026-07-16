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
    <body class="overflow-x-hidden font-sans antialiased text-sh-text">
        <div class="min-h-screen overflow-x-hidden brand-page-bg">
            @include('layouts.navigation')

            <main class="overflow-x-hidden pb-12">
                @isset($header)
                    <div class="border-b border-sh-border/80 bg-white/75 backdrop-blur-md">
                        <div class="admin-shell !pb-5 !pt-6">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
