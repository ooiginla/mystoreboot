@extends('auth.layout')

@section('title', 'Choose a new password · Storeboot')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Choose a new password</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Set a strong password you'll remember. You'll use it to sign in from now on.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @php $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white'; $lbl = 'mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300'; @endphp

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="{{ $lbl }}">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required class="{{ $field }}" placeholder="you@business.com">
        </div>
        <div>
            <label for="password" class="{{ $lbl }}">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required class="{{ $field }}" placeholder="At least 8 characters">
        </div>
        <div>
            <label for="password_confirmation" class="{{ $lbl }}">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="{{ $field }}" placeholder="Re-enter password">
        </div>
        <button type="submit" class="sb-btn sb-btn-primary w-full py-3 text-base">Reset password</button>
    </form>

    <p class="mt-8 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Back to sign in</a>
    </p>
@endsection
