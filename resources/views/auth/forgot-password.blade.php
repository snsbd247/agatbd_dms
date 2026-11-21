<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password · DMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <form method="post" action="{{ route('password.email') }}" class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
        @csrf
        <h1 class="text-2xl font-bold text-slate-900">Forgot your password?</h1>
        <p class="text-slate-500 mt-1 mb-6 text-sm">
            @if ($mailEnabled)
                Enter your email and we'll send you a link to set a new password.
            @else
                Enter your email and your administrator will be notified to set a new password for you.
            @endif
        </p>

        @if (session('status'))
            <p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">{{ session('status') }}</p>
        @endif

        <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" class="w-full border rounded-lg p-3 mb-4" required autofocus autocomplete="username">
        @error('email') <p class="text-red-600 text-sm -mt-2 mb-3">{{ $message }}</p> @enderror

        <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-3 font-semibold">
            {{ $mailEnabled ? 'Send reset link' : 'Notify administrator' }}
        </button>

        <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500 hover:text-indigo-600 mt-5">← Back to sign in</a>
    </form>
</body>
</html>
