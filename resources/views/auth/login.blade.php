@extends('auth.layout')

@section('title', 'Sign in · Storeboot')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Welcome back</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Sign in to your Storeboot workspace.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
            <p class="font-medium">{{ $errors->first() }}</p>
            @if (session('unverified_email'))
                <form class="mt-3" method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ session('unverified_email') }}">
                    <p class="mb-2 text-red-600/90 dark:text-red-300/80">Check your inbox and spam. Missed it? Resend the verification email.</p>
                    <button type="submit" class="sb-btn sb-btn-ghost w-full !py-2 text-xs">Resend verification email</button>
                </form>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus
                class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white"
                placeholder="you@business.com">
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white"
                placeholder="••••••••">
        </div>

        <label class="flex items-center gap-2.5 text-sm text-zinc-600 dark:text-zinc-400">
            <input name="remember" type="checkbox" value="1" class="h-4 w-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-500 dark:border-white/20 dark:bg-white/5">
            Keep me signed in
        </label>

        <button type="submit" class="sb-btn sb-btn-primary w-full py-3 text-base">Sign in</button>
    </form>

    <p class="mt-8 text-center text-sm text-zinc-600 dark:text-zinc-400">
        New to Storeboot?
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Create an account</a>
    </p>
@endsection
