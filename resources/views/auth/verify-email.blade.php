@extends('auth.layout')

@section('title', 'Verify email · Storeboot')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Check your email</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Enter the six-digit verification code we sent you.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('verification.verify') }}" class="space-y-5">
        @csrf
        <div>
            <label for="verification-email" class="mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Email address</label>
            <input id="verification-email" name="email" type="email" value="{{ $email }}" autocomplete="email" required
                class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white">
        </div>
        <div>
            <label for="verification-code" class="mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Verification code</label>
            <input id="verification-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-center font-mono text-2xl font-bold tracking-[0.4em] text-zinc-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white"
                placeholder="000000">
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">The code expires after 15 minutes.</p>
        </div>
        <button type="submit" class="sb-btn sb-btn-primary w-full py-3 text-base">Verify and continue</button>
    </form>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-5 text-center">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <span class="text-sm text-zinc-500 dark:text-zinc-400">Didn't receive the code?</span>
        <button type="submit" class="ml-1 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Resend code</button>
    </form>

    <p class="mt-8 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-brand-600 dark:text-brand-400">Back to sign in</a></p>
@endsection
