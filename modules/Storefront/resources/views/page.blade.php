@extends('storefront::layout', ['title' => $title.' | '.$store->store_name])

@section('content')
    <section class="store-shell py-14">
        <div class="mx-auto max-w-3xl">
            <p class="text-sm font-black uppercase tracking-[.18em] text-[var(--store-secondary)]">{{ $store->store_name }}</p>
            <h1 class="store-display mt-3 text-4xl font-black text-[var(--store-primary)]">{{ $title }}</h1>
            <div class="store-card mt-8 p-6 leading-8 text-[var(--store-muted)] md:p-8">
                @if ($content)
                    {!! nl2br(e($content)) !!}
                @else
                    <p>{{ $title }} content has not been published yet.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
