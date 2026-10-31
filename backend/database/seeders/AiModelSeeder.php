<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        $defaultNebiusModel = config('ai.nebius.default_model', 'nvidia/nemotron-3-super-120b-a12b');
        if (str_starts_with((string) $defaultNebiusModel, 'meta-llama/Meta-Llama-3.1-')) {
            $defaultNebiusModel = 'nvidia/nemotron-3-super-120b-a12b';
        }

        $fastNebiusModel = config('ai.nebius.fast_model', 'Qwen/Qwen3-30B-A3B-Instruct-2507');
        if (str_starts_with((string) $fastNebiusModel, 'meta-llama/Meta-Llama-3.1-')) {
            $fastNebiusModel = 'Qwen/Qwen3-30B-A3B-Instruct-2507';
        }

        $nebiusBaseUrl = config('ai.nebius.base_url', 'https://api.tokenfactory.nebius.com/v1');
        if (str_contains((string) $nebiusBaseUrl, 'api.studio.nebius.com')) {
            $nebiusBaseUrl = 'https://api.tokenfactory.nebius.com/v1';
        }

        // Migrate any legacy Nebius rows from earlier seeds in-place
        AiModel::where('provider_slug', 'nebius')
            ->whereIn('model_id', ['meta-llama/Meta-Llama-3.1-70B-Instruct', 'meta-llama/Meta-Llama-3.1-8B-Instruct'])
            ->delete();

        $models = [
            [
                'provider_slug' => 'nebius',
                'display_name' => 'NVIDIA Nemotron-3 Super 120B (Nebius Token Factory)',
                'base_url' => $nebiusBaseUrl,
                'api_key_env' => 'NEBIUS_API_KEY',
                'model_id' => $defaultNebiusModel,
                'model_family' => 'nemotron-3-super',
                'task_role' => 'architect',
                'input_cost_per_1m' => 0.200000,
                'output_cost_per_1m' => 0.600000,
                'token_multiplier' => 2.00,
                'input_multiplier' => 2.00,
                'output_multiplier' => 2.50,
                'supports_vision' => false,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => true,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 8192,
                'default_temperature' => 0.20,
                'context_budget_max_tokens' => 32000,
                'default_extra_payload' => ['temperature' => 0.2],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Primary NVIDIA Nemotron-3 Super 120B model on Nebius Token Factory for 3D circuit synthesis, Flownie Search Mode, and CircuitSDK Code Mode execution.',
            ],
            [
                'provider_slug' => 'nebius',
                'display_name' => 'Qwen3 30B Instruct — Real-Time Fault & Short-Circuit Diagnostics',
                'base_url' => $nebiusBaseUrl,
                'api_key_env' => 'NEBIUS_API_KEY',
                'model_id' => $fastNebiusModel,
                'model_family' => 'qwen3-30b',
                'task_role' => 'fast_diagnose',
                'input_cost_per_1m' => 0.060000,
                'output_cost_per_1m' => 0.180000,
                'token_multiplier' => 1.80,
                'input_multiplier' => 1.80,
                'output_multiplier' => 2.00,
                'supports_vision' => false,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => false,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 4096,
                'default_temperature' => 0.15,
                'context_budget_max_tokens' => 16000,
                'default_extra_payload' => ['temperature' => 0.15],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Low-latency hardware fault diagnostics, nodal voltage inspection, and pin conflict resolution on Nebius Token Factory.',
            ],
            [
                'provider_slug' => 'nebius',
                'display_name' => 'NVIDIA Nemotron-3 Super 120B — Datasheet Pinout & 3D Mesh Synthesizer',
                'base_url' => $nebiusBaseUrl,
                'api_key_env' => 'NEBIUS_API_KEY',
                'model_id' => $defaultNebiusModel,
                'model_family' => 'nemotron-3-super',
                'task_role' => 'datasheet_synth',
                'input_cost_per_1m' => 0.200000,
                'output_cost_per_1m' => 0.600000,
                'token_multiplier' => 2.00,
                'input_multiplier' => 2.00,
                'output_multiplier' => 2.50,
                'supports_vision' => false,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => false,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 8192,
                'default_temperature' => 0.10,
                'context_budget_max_tokens' => 24000,
                'default_extra_payload' => ['temperature' => 0.1],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'Extracts pinout, voltage tolerances, internal dipoles, and 3D procedural geometry from Tavily web datasheets.',
            ],
            [
                'provider_slug' => 'nebius',
                'display_name' => 'Qwen3 235B Instruct (Nebius Token Factory)',
                'base_url' => $nebiusBaseUrl,
                'api_key_env' => 'NEBIUS_API_KEY',
                'model_id' => 'Qwen/Qwen3-235B-A22B-Instruct-2507',
                'model_family' => 'qwen3-235b',
                'task_role' => 'architect',
                'input_cost_per_1m' => 0.200000,
                'output_cost_per_1m' => 0.600000,
                'token_multiplier' => 2.00,
                'input_multiplier' => 2.00,
                'output_multiplier' => 2.50,
                'supports_vision' => false,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => false,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 8192,
                'default_temperature' => 0.20,
                'context_budget_max_tokens' => 32000,
                'default_extra_payload' => ['temperature' => 0.2],
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 4,
                'description' => '235B MoE hardware architect on Nebius Token Factory for complex multi-component synthesis and firmware generation.',
            ],
            [
                'provider_slug' => 'nebius',
                'display_name' => 'DeepSeek V4.1 Flash (Nebius Token Factory)',
                'base_url' => $nebiusBaseUrl,
                'api_key_env' => 'NEBIUS_API_KEY',
                'model_id' => 'deepseek-ai/DeepSeek-V4.1-Flash',
                'model_family' => 'deepseek-v4.1',
                'task_role' => 'architect',
                'input_cost_per_1m' => 0.100000,
                'output_cost_per_1m' => 0.300000,
                'token_multiplier' => 1.80,
                'input_multiplier' => 1.80,
                'output_multiplier' => 2.00,
                'supports_vision' => false,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => false,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 8192,
                'default_temperature' => 0.20,
                'context_budget_max_tokens' => 32000,
                'default_extra_payload' => ['temperature' => 0.2],
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 5,
                'description' => 'Ultra-fast Nebius Token Factory model for rapid CircuitSDK scripting and interactive electronics tutoring.',
            ],
            [
                'provider_slug' => 'gemini_compat',
                'display_name' => 'Google Gemini 2.5 Flash (OpenAI-Compatible Endpoint)',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'api_key_env' => 'GEMINI_API_KEY',
                'model_id' => 'gemini-2.5-flash',
                'model_family' => 'gemini-2.5',
                'task_role' => 'architect',
                'input_cost_per_1m' => 0.150000,
                'output_cost_per_1m' => 0.600000,
                'token_multiplier' => 2.00,
                'input_multiplier' => 2.00,
                'output_multiplier' => 2.50,
                'supports_vision' => true,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => true,
                'max_input_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'default_temperature' => 0.25,
                'context_budget_max_tokens' => 64000,
                'default_extra_payload' => ['temperature' => 0.2],
                'is_default' => false,
                'is_active' => !empty(env('GEMINI_API_KEY')),
                'sort_order' => 6,
                'description' => 'Fast multimodal circuit architect with 1M context window and tool calling.',
            ],
            [
                'provider_slug' => 'openai',
                'display_name' => 'OpenAI GPT-4o (Hardware & Firmware Architect)',
                'base_url' => 'https://api.openai.com/v1',
                'api_key_env' => 'OPENAI_API_KEY',
                'model_id' => 'gpt-4o',
                'model_family' => 'gpt-4o',
                'task_role' => 'architect',
                'input_cost_per_1m' => 2.500000,
                'output_cost_per_1m' => 10.000000,
                'token_multiplier' => 2.00,
                'input_multiplier' => 1.80,
                'output_multiplier' => 2.20,
                'supports_vision' => true,
                'supports_function_calling' => true,
                'supports_code_mode' => true,
                'supports_thinking' => false,
                'max_input_tokens' => 128000,
                'max_output_tokens' => 16384,
                'default_temperature' => 0.20,
                'context_budget_max_tokens' => 32000,
                'default_extra_payload' => ['temperature' => 0.2],
                'is_default' => false,
                'is_active' => !empty(env('OPENAI_API_KEY')),
                'sort_order' => 7,
                'description' => 'High-precision hardware schematic and embedded firmware code generator.',
            ],
        ];

        foreach ($models as $modelData) {
            AiModel::firstOrCreate(
                [
                    'provider_slug' => $modelData['provider_slug'],
                    'task_role' => $modelData['task_role'],
                    'display_name' => $modelData['display_name'],
                ],
                $modelData
            );
        }
    }
}
