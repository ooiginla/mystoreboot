@extends('storefront::layout', ['title' => 'FAQ | '.$store->store_name])

@section('content')
    <section class="store-shell py-14">
        <div class="mx-auto max-w-3xl">
            <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $store->store_name }}</p>
            <h1 class="sf-headline-lg mt-3 text-[var(--store-primary)]">FAQ</h1>
            <div class="mt-8 grid gap-3">
                @forelse ((array) $store->faqs as $faq)
                    <details class="store-card p-5">
                        <summary class="sf-body-md cursor-pointer font-bold">{{ $faq['question'] ?? 'Question' }}</summary>
                        <p class="sf-body-md mt-3 text-[var(--store-muted)]">{{ $faq['answer'] ?? '' }}</p>
                    </details>
                @empty
                    <div class="sf-body-md store-card p-8 text-center text-[var(--store-muted)]">FAQ content has not been published yet.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
