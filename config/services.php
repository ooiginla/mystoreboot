<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'paystack' => [
        'public_key' => env('STOREBOOT_PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('STOREBOOT_PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'recaptcha' => [
        'site_key' => env('GOOGLE_RECAPTCHA_SITE_KEY'),
        'secret_key' => env('GOOGLE_RECAPTCHA_SECRET_KEY'),
        'verify_url' => env('GOOGLE_RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Powers AI drafting of product name/description/price from a photo.
    // When the selected provider has no key, the importer still creates draft
    // products — it just leaves the AI-filled fields blank for the merchant.
    'ai' => [
        // Which provider drafts products from photos: "anthropic" or "openai".
        'provider' => env('AI_PROVIDER', 'anthropic'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        // Latest Opus by default; set ANTHROPIC_MODEL=claude-haiku-4-5 for cheaper bulk imports.
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    ],

    'openai' => [
        // Requires an API key from platform.openai.com (billed separately from
        // any ChatGPT subscription), not the chat.openai.com login.
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
        // gpt-4o-mini is cheap and strong for product drafting; set OPENAI_MODEL to override.
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        // Text-to-image model for auto-generating product photos (OpenAI only).
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
    ],

];
