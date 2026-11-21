<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reset Password · DMS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <div class="fixed top-4 right-4"><x-locale-switch :dark="true" /></div>
    <form method="post" action="{{ route('password.update') }}" class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Set a new password') }}</h1>
        <p class="text-slate-500 mt-1 mb-6 text-sm">{{ __('At least 8 characters, with letters and numbers.') }}</p>

        <input name="email" type="email" placeholder="{{ __('Email') }}" value="{{ old('email', $email) }}" class="w-full border rounded-lg p-3 mb-3" required autocomplete="username">
        <input name="password" type="password" placeholder="{{ __('New password') }}" class="w-full border rounded-lg p-3 mb-3" required autocomplete="new-password">
        <input name="password_confirmation" type="password" placeholder="{{ __('Confirm new password') }}" class="w-full border rounded-lg p-3 mb-4" required autocomplete="new-password">

        @if ($errors->any())
            <p class="text-red-600 text-sm mb-4">{{ $errors->first() }}</p>
        @endif

        <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-3 font-semibold">{{ __('Reset password') }}</button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500 hover:text-indigo-600 mt-5">{{ __('← Back to sign in') }}</a>
    </form>
</body>
</html>
