@extends('marketing.layout')

@section('title', 'Contact us · Storeboot')
@section('meta_description', 'Get in touch with the Storeboot team — email, phone, and WhatsApp support for your business.')

@section('content')
    <section class="relative overflow-hidden pt-32 pb-20 sm:pt-40">
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-[440px] w-[760px] -translate-x-1/2 rounded-full bg-brand-400/15 blur-3xl dark:bg-brand-500/10"></div>
        <div class="pointer-events-none absolute inset-0 -z-10 text-brand-600/60 dark:text-brand-400/20"><div class="sb-grid-bg absolute inset-0"></div></div>

        <div class="sb-container">
            <div class="mx-auto max-w-2xl text-center">
                <span class="sb-eyebrow">We're here to help</span>
                <h1 class="mt-5 font-display text-4xl font-bold leading-tight tracking-tight text-zinc-900 sm:text-5xl dark:text-white">Let's talk about your business</h1>
                <p class="mt-5 sb-lead">Questions, demos, or help getting set up — reach the Storeboot team any way you like. We usually reply within a few hours.</p>
            </div>

            <div class="mx-auto mt-14 grid max-w-5xl gap-6 lg:grid-cols-[1fr_1.1fr]">
                {{-- Contact details --}}
                <div class="space-y-4">
                    @php
                        $waGroup = 'https://chat.whatsapp.com/Iy1epwuwKIC2SAIEVMjAKa?mode=gi_t';
                        $channels = [
                            ['label' => 'Email us', 'value' => 'support@storeboot.com', 'href' => 'mailto:support@storeboot.com', 'ext' => false,
                             'icon' => 'M3 7l9 6 9-6M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                            ['label' => 'Call us', 'value' => '0703 536 1770', 'href' => 'tel:+2347035361770', 'ext' => false,
                             'icon' => 'M3 5a2 2 0 0 1 2-2h2.5a1 1 0 0 1 1 .76l1 4a1 1 0 0 1-.29.95L7.6 11.6a12 12 0 0 0 4.8 4.8l1.9-1.9a1 1 0 0 1 .95-.29l4 1a1 1 0 0 1 .76 1V19a2 2 0 0 1-2 2A16 16 0 0 1 3 5Z'],
                            ['label' => 'Website', 'value' => 'www.storeboot.com', 'href' => 'https://www.storeboot.com', 'ext' => true,
                             'icon' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 0c2.5 2.5 3.5 6 3.5 9s-1 6.5-3.5 9m0-18c-2.5 2.5-3.5 6-3.5 9s1 6.5 3.5 9M3.5 9h17M3.5 15h17'],
                        ];
                    @endphp

                    @foreach ($channels as $c)
                        <a href="{{ $c['href'] }}" @if($c['ext']) target="_blank" rel="noopener" @endif
                           class="sb-card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:border-brand-300 dark:hover:border-brand-500/40">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500/12 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white dark:text-brand-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-bold uppercase tracking-widest text-zinc-400">{{ $c['label'] }}</span>
                                <span class="block truncate font-display text-lg font-bold text-zinc-900 dark:text-white">{{ $c['value'] }}</span>
                            </span>
                        </a>
                    @endforeach

                    <a href="{{ $waGroup }}" target="_blank" rel="noopener"
                       class="group flex items-center gap-4 rounded-3xl border border-[#25D366]/30 bg-[#25D366]/10 p-5 transition hover:-translate-y-0.5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#25D366] text-white">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.9-.8-1.5-1.77-1.67-2.07-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.6-.92-2.2-.24-.58-.48-.5-.67-.5l-.57-.02c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.47 0 1.46 1.07 2.87 1.22 3.07.15.2 2.1 3.2 5.08 4.48.71.3 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35Z"/><path d="M12 2a10 10 0 0 0-8.5 15.27L2 22l4.85-1.27A10 10 0 1 0 12 2Zm0 1.9a8.1 8.1 0 0 1 6.9 12.36l-.28.44.66 2.4-2.46-.65-.42.25A8.1 8.1 0 1 1 12 3.9Z"/></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-bold uppercase tracking-widest text-[#128C4B]">WhatsApp community</span>
                            <span class="block truncate font-display text-lg font-bold text-zinc-900 dark:text-white">Join our group</span>
                        </span>
                    </a>

                    <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50 p-5 text-sm text-zinc-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-zinc-400">
                        Storeboot is a product of <strong class="text-zinc-700 dark:text-zinc-200">The Bootup Limited</strong>, registered in Nigeria.
                    </div>
                </div>

                {{-- Contact form --}}
                <div class="sb-card p-7 sm:p-8">
                    @if (session('status'))
                        <div class="mb-6 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
                            <ul class="list-disc space-y-0.5 pl-5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @php $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 outline-none transition placeholder:text-zinc-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-white/10 dark:bg-white/5 dark:text-white'; $lbl = 'mb-1.5 block text-sm font-semibold text-zinc-700 dark:text-zinc-300'; @endphp

                    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="{{ $lbl }}">Your name</label>
                                <input id="name" name="name" value="{{ old('name') }}" required class="{{ $field }}" placeholder="Amaka Obi">
                            </div>
                            <div>
                                <label for="email" class="{{ $lbl }}">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="{{ $field }}" placeholder="you@business.com">
                            </div>
                        </div>
                        <div>
                            <label for="subject" class="{{ $lbl }}">Subject</label>
                            <input id="subject" name="subject" value="{{ old('subject') }}" class="{{ $field }}" placeholder="How can we help?">
                        </div>
                        <div>
                            <label for="message" class="{{ $lbl }}">Message</label>
                            <textarea id="message" name="message" rows="5" required class="{{ $field }}" placeholder="Tell us a little about your business and what you need...">{{ old('message') }}</textarea>
                        </div>
                        <button type="submit" class="sb-btn sb-btn-primary w-full py-3 text-base">
                            Send message
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
