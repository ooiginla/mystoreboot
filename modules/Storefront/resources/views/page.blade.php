@extends('storefront::layout', ['title' => $title.' | '.$store->store_name])

@push('styles')
    <style>
        .store-rich-text { overflow-wrap: anywhere; }
        .store-rich-text h2 { margin: 1.5rem 0 .55rem; color: var(--store-ink); font-size: 1.55rem; line-height: 1.3; font-weight: 700; }
        .store-rich-text h3 { margin: 1.25rem 0 .45rem; color: var(--store-ink); font-size: 1.25rem; line-height: 1.35; font-weight: 650; }
        .store-rich-text p { margin: 0 0 1rem; }
        .store-rich-text ul, .store-rich-text ol { margin: .75rem 0 1rem 1.5rem; }
        .store-rich-text ul { list-style: disc; }
        .store-rich-text ol { list-style: decimal; }
        .store-rich-text li + li { margin-top: .35rem; }
        .store-rich-text blockquote { margin: 1rem 0; padding-left: 1rem; border-left: 3px solid var(--store-secondary); color: var(--store-ink); }
        .store-rich-text a { color: var(--store-secondary); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>
@endpush

@section('content')
    <section class="store-shell py-14">
        <div class="mx-auto max-w-3xl">
            <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $store->store_name }}</p>
            <h1 class="sf-headline-lg mt-3 text-[var(--store-primary)]">{{ $title }}</h1>
            <div class="sf-body-lg store-rich-text store-card mt-8 p-6 text-[var(--store-muted)] md:p-8">
                @if ($content)
                    {!! app(\Modules\Business\Support\SafeRichText::class)->render($content) !!}
                @else
                    <p>{{ $title }} content has not been published yet.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
