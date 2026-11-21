<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Forgot Password · DMS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <div class="fixed top-4 right-4"><x-locale-switch :dark="true" /></div>
    <form method="post" action="{{ route('password.email') }}" class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
        @csrf
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Forgot your password?') }}</h1>
        <p class="text-slate-500 mt-1 mb-6 text-sm">
            @if ($mailEnabled)
                {{ __("Enter your email and we'll send you a link to set a new password.") }}
            @else
                {{ __('Enter your email and your administrator will be notified to set a new password for you.') }}
            @endif
        </p>

        @if (session('status'))
            <p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">{{ session('status') }}</p>
        @endif

        <input name="email" type="email" placeholder="{{ __('Email') }}" value="{{ old('email') }}" class="w-full border rounded-lg p-3 mb-4" required autofocus autocomplete="username">
        @error('email') <p class="text-red-600 text-sm -mt-2 mb-3">{{ $message }}</p> @enderror

        <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-3 font-semibold">
            {{ $mailEnabled ? __('Send reset link') : __('Notify administrator') }}
        </button>

        <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500 hover:text-indigo-600 mt-5">{{ __('← Back to sign in') }}</a>
    </form>
</body>
</html>
