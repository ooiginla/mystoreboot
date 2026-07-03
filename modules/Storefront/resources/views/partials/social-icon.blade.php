@php
    $class = $class ?? 'h-5 w-5';
@endphp

@switch($network)
    @case('facebook')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.2 8.2V6.7c0-.7.5-.9.9-.9h2.4V2.1L14.2 2c-3.7 0-4.5 2.7-4.5 4.5v1.7H6.8V12h2.9v10h4.1V12h3.1l.5-3.8h-3.2Z"/></svg>
        @break
    @case('instagram')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect width="16" height="16" x="4" y="4" rx="4" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3.4" stroke="currentColor" stroke-width="2"/><circle cx="17" cy="7" r="1.2" fill="currentColor"/></svg>
        @break
    @case('tiktok')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 2c.4 3 2.1 4.8 5.1 5v3.4a8.5 8.5 0 0 1-5-1.5v6.8c0 3.4-2.3 6.3-6.4 6.3-3.5 0-6-2.3-6-5.6 0-3.8 3.4-6.3 7.1-5.5v3.5c-1.8-.6-3.4.3-3.4 1.9 0 1.2.9 2.1 2.2 2.1 1.5 0 2.4-.9 2.4-2.8V2h4Z"/></svg>
        @break
    @case('twitter')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.3 2h3.4l-7.5 8.6L23 22h-6.9l-5.4-7-6.2 7H1.1l8-9.2L.7 2h7.1l4.9 6.5L18.3 2Zm-1.2 18h1.9L6.8 3.9H4.7L17.1 20Z"/></svg>
        @break
    @case('youtube')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/></svg>
        @break
    @case('whatsapp')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2A9.7 9.7 0 0 0 3.6 16.8l-1.1 4.3 4.4-1.1A9.7 9.7 0 1 0 12 2.2Zm0 17.6a7.8 7.8 0 0 1-4-1.1l-.3-.2-2.6.7.7-2.5-.2-.3a7.8 7.8 0 1 1 6.4 3.4Zm4.5-5.8c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.4 6.4 0 0 1-3.2-2.8c-.2-.3 0-.4.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.7-1.6c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.3.3-1 1-1 2.3s1 2.7 1.1 2.9c.1.2 2 3.1 4.9 4.3 1.8.8 2.5.8 3.4.7 1-.1 2.4-1 2.7-2 .4-.9.4-1.7.3-1.9-.1-.1-.3-.2-.6-.3Z"/></svg>
        @break
    @default
        @include('storefront::partials.icon', ['name' => 'link', 'class' => $class])
@endswitch
