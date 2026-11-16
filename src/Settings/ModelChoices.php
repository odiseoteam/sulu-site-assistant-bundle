<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Settings;

/**
 * The models and efforts a webspace can pick in the admin, each model with its platform. Each
 * one has a price in the core, so the agent can start with it.
 */
final class ModelChoices
{
    public const MODELS = [
        'claude-haiku-5-5' => ['platform' => 'anthropic', 'label' => 'Claude Haiku 5.5'],
        'claude-sonnet-5' => ['platform' => 'anthropic', 'label' => 'Claude Sonnet 5'],
        'claude-sonnet-5-5' => ['platform' => 'anthropic', 'label' => 'Claude Sonnet 5.5'],
        'gpt-5.6-luna' => ['platform' => 'openai', 'label' => 'OpenAI Luna'],
    ];

    /** Without thinking the reply comes out worse on every model, so off is not offered. */
    public const EFFORTS = ['low', 'medium', 'high'];

    public const PROVIDERS = ['anthropic' => 'Anthropic', 'openai' => 'OpenAI'];

    public static function platformOf(string $model): ?string
    {
        return self::MODELS[$model]['platform'] ?? null;
    }

    /** "Claude Haiku 5.5 (Anthropic)" for a known model; the id, with the provider its name gives away, for another. */
    public static function describe(string $model): string
    {
        $platform = self::platformOf($model) ?? match (true) {
            \str_starts_with($model, 'claude-') => 'anthropic',
            \str_starts_with($model, 'gpt-'), 1 === \preg_match('/^o\d/', $model) => 'openai',
            default => null,
        };
        $label = self::MODELS[$model]['label'] ?? $model;

        return null === $platform ? $label : \sprintf('%s (%s)', $label, self::PROVIDERS[$platform] ?? $platform);
    }

    /** @return list<string> */
    public static function models(): array
    {
        return \array_keys(self::MODELS);
    }
}
