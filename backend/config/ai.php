<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default OpenAI-Compatible / Multimodal & Qloo Configuration
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'nebius'),

    'daily_live_llm_cap' => (int) env('DAILY_LIVE_LLM_CAP', 250),

    'nebius' => [
        'base_url' => env('NEBIUS_BASE_URL', 'https://api.tokenfactory.nebius.com/v1'),
        'api_key' => env('NEBIUS_API_KEY', ''),
        'default_model' => env('NEBIUS_DEFAULT_MODEL', 'nvidia/nemotron-3-super-120b-a12b'),
        'fast_model' => env('NEBIUS_FAST_MODEL', 'Qwen/Qwen3-30B-A3B-Instruct-2507'),
        'vision_model' => env('NEBIUS_VISION_MODEL', 'google/gemma-3-27b-it'),
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY', ''),
        'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'deepseek/deepseek-chat'),
        'fast_model' => env('OPENROUTER_FAST_MODEL', 'deepseek/deepseek-chat'),
        'vision_model' => env('OPENROUTER_VISION_MODEL', 'google/gemma-3-27b-it'),
    ],

    'qloo' => [
        'server_url' => env('QLOO_SERVER_URL', env('QLOO_BASE_URL', 'https://hackathon.api.qloo.com')),
        'base_url' => env('QLOO_SERVER_URL', env('QLOO_BASE_URL', 'https://hackathon.api.qloo.com')),
        'api_key' => env('QLOO_API_KEY', ''),
        'cache_ttl_hours' => (int) env('QLOO_CACHE_TTL_HOURS', 168),
    ],

    'cloudflare_gateway' => [
        'enabled' => (bool) env('CF_AIG_ENABLED', false),
        'base_url' => env('CF_AIG_BASE_URL', ''),
        'token' => env('CF_AIG_TOKEN', ''),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY', ''),
    ],

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
        'api_key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Config-Cache Safe Provider API Key Lookup Map (for Dokploy / Production)
    |--------------------------------------------------------------------------
    */
    'env_keys' => [
        'NEBIUS_API_KEY' => env('NEBIUS_API_KEY', ''),
        'QLOO_SERVER_URL' => env('QLOO_SERVER_URL', ''),
        'QLOO_API_KEY' => env('QLOO_API_KEY', ''),
        'OPENAI_API_KEY' => env('OPENAI_API_KEY', ''),
        'GEMINI_API_KEY' => env('GEMINI_API_KEY', ''),
        'OPENROUTER_API_KEY' => env('OPENROUTER_API_KEY', ''),
        'OPENROUTER_BASE_URL' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'OPENROUTER_DEFAULT_MODEL' => env('OPENROUTER_DEFAULT_MODEL', 'deepseek/deepseek-chat'),
        'OPENROUTER_FAST_MODEL' => env('OPENROUTER_FAST_MODEL', 'deepseek/deepseek-chat'),
        'OPENROUTER_VISION_MODEL' => env('OPENROUTER_VISION_MODEL', 'google/gemma-3-27b-it'),
        'ANTHROPIC_API_KEY' => env('ANTHROPIC_API_KEY', ''),
    ],
];
