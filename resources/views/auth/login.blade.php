<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Brand::title(__('Login')) }}</title>
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <div class="fixed top-4 right-4"><x-locale-switch :dark="true" /></div>
    <form method="post" action="{{ route('login.attempt') }}" class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
        @csrf
        <div class="text-center mb-8">
            @if ($brandLogo = \App\Support\Brand::logoUrl())
                <img src="{{ $brandLogo }}" alt="" class="h-24 mx-auto mb-3 object-contain">
            @endif
            <h1 class="text-2xl font-bold text-slate-900">{{ \App\Support\Brand::name() }}</h1>
            <p class="text-slate-500">{{ __('Distributor Management System') }}</p>
        </div>

        @if (session('status'))
            <p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">{{ session('status') }}</p>
        @endif

        <input name="email" type="email" placeholder="{{ __('Email') }}" value="{{ old('email') }}" class="w-full border rounded-lg p-3 mb-3" required autofocus autocomplete="username">
        <input name="password" type="password" placeholder="{{ __('Password') }}" class="w-full border rounded-lg p-3 mb-2" required autocomplete="current-password">
        <div class="text-right mb-5">
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Forgot password?') }}</a>
        </div>
        <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-3 font-semibold">{{ __('Sign in') }}</button>
        @if ($errors->any())
            <p class="text-red-600 mt-4">{{ $errors->first() }}</p>
        @endif
    </form>
</body>
</html>
