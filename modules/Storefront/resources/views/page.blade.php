@extends('storefront::layout', ['title' => $title.' | '.$store->store_name])

@section('content')
    <section class="store-shell py-14">
        <div class="mx-auto max-w-3xl">
            <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $store->store_name }}</p>
            <h1 class="sf-headline-lg mt-3 text-[var(--store-primary)]">{{ $title }}</h1>
            <div class="sf-body-lg store-card mt-8 p-6 text-[var(--store-muted)] md:p-8">
                @if ($content)
                    {!! nl2br(e($content)) !!}
                @else
                    <p>{{ $title }} content has not been published yet.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
