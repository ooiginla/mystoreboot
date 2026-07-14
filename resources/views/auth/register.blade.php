@extends('auth.layout')

@section('title', 'Create your account · Storeboot')
@section('formWidth', 'max-w-xl')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Create your workspace</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Start your free 14-day trial. No card required — verify your email and you're in.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
            <p class="font-semibold">Please check the highlighted details:</p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white';
        $labelCls = 'mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300';
    @endphp

    <form method="POST" action="{{ route('register.store') }}" class="space-y-7">
        @csrf

        {{-- Business --}}
        <fieldset>
            <legend class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                <span class="grid h-5 w-5 place-items-center rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400">1</span>
                About your business
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="business_name" class="{{ $labelCls }}">Business name</label>
                    <input id="business_name" name="business_name" value="{{ old('business_name') }}" required autofocus class="{{ $field }}" placeholder="e.g. FreshMart Grocery">
                </div>
                <div>
                    <label for="business_category" class="{{ $labelCls }}">Business category</label>
                    <select id="business_category" name="business_category" required class="{{ $field }}">
                        <option value="">Select category</option>
                        @foreach ($businessCategories as $value => $label)
                            <option value="{{ $value }}" @selected(old('business_category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="country" class="{{ $labelCls }}">Country</label>
                    <select id="country" name="country" required class="{{ $field }}">
                        <option value="">Select country</option>
                        @foreach ($countries as $code => $name)
                            <option value="{{ $code }}" @selected(old('country', 'NG') === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="city" class="{{ $labelCls }}">City</label>
                    <input id="city" name="city" value="{{ old('city') }}" required class="{{ $field }}" placeholder="e.g. Lagos">
                </div>
            </div>
        </fieldset>

        {{-- Account --}}
        <fieldset>
            <legend class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                <span class="grid h-5 w-5 place-items-center rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400">2</span>
                Your account
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="{{ $labelCls }}">Full name</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required class="{{ $field }}" placeholder="Amaka Obi">
                </div>
                <div>
                    <label for="email" class="{{ $labelCls }}">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="{{ $field }}" placeholder="you@business.com">
                </div>
                <div>
                    <label for="password" class="{{ $labelCls }}">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="{{ $field }}" placeholder="At least 8 characters">
                </div>
                <div>
                    <label for="password_confirmation" class="{{ $labelCls }}">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="{{ $field }}" placeholder="Re-enter password">
                </div>
            </div>
        </fieldset>

        @if ($recaptchaEnabled)
            {{-- Verification --}}
            <fieldset>
                <legend class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-zinc-400">
                    <span class="grid h-5 w-5 place-items-center rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-400">3</span>
                    Human verification
                </legend>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">This helps us keep automated signups away.</p>
                </div>
            </fieldset>
        @endif

        <button type="submit" class="sb-btn sb-btn-primary w-full py-3 text-base">
            Create account
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
        </button>

        <p class="text-center text-xs text-zinc-500 dark:text-zinc-500">
            By creating an account you agree to our
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-semibold text-brand-600 dark:text-brand-400">Terms</a> and
            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-600 dark:text-brand-400">Privacy Policy</a>.
        </p>
    </form>

    <p class="mt-8 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Sign in</a>
    </p>
@endsection

@if ($recaptchaEnabled)
    @push('scripts')
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endpush
@endif
