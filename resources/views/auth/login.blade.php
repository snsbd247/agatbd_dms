<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DMS Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center">
    <form method="post" action="{{ route('login.attempt') }}" class="bg-white p-8 rounded-2xl w-full max-w-md shadow-2xl">
        @csrf
        <h1 class="text-3xl font-bold text-slate-900">DMS Portal</h1>
        <p class="text-slate-500 mb-8">Distributor Management System</p>
        <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" class="w-full border rounded-lg p-3 mb-3" required autofocus>
        <input name="password" type="password" placeholder="Password" class="w-full border rounded-lg p-3 mb-6" required>
        <button class="w-full bg-indigo-600 text-white rounded-lg p-3 font-semibold">Sign in</button>
        @if ($errors->any())
            <p class="text-red-600 mt-4">{{ $errors->first() }}</p>
        @endif
    </form>
</body>
</html>
