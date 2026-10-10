<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Twig;

use Symfony\Bridge\Twig\AppVariable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `{{ site_assistant_widget() }}` renders the widget where a template wants it. Optional
 * `chips` replace the starter questions for that page.
 */
final class WidgetExtension extends AbstractExtension
{
    /** The strings the JS reads; the rest are rendered by the template itself. */
    public const JS_KEYS = [
        'card.recommended',
        'card.pros',
        'card.cons',
        'card.best_for',
        'card.retry',
        'card.carousel.prev',
        'card.carousel.next',
        'card.comparison.compared_on',
        'card.handoff.summary',
        'card.handoff.route.page',
        'card.handoff.route.email',
        'card.handoff.route.booking',
        'widget.input.placeholder',
        'widget.input.placeholder_busy',
        'widget.expand',
        'widget.collapse',
        'widget.status.thinking',
        'tool.search_content',
        'tool.get_content',
        'tool.recall_memories',
        'tool.save_memory',
        'tool.load_skill',
        'tool.presenting',
        'error.js.connection_lost',
        'error.js.unavailable',
        'error.http.too_many_sessions',
        'error.http.too_many_messages',
        'error.http.empty_message',
        'error.http.not_configured',
        'error.http.turn_failed',
        'error.http.budget_exceeded',
    ];

    /** @param list<string> $contentTypes type names, whose `card.type.*` labels the JS reads */
    public function __construct(
        private readonly string $template,
        private readonly array $contentTypes = [],
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_assistant_widget', $this->render(...), ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{chips?: list<string>, locale?: string} $options
     */
    public function render(Environment $twig, array $context, array $options = []): string
    {
        $app = $context['app'] ?? null;
        $request = $app instanceof AppVariable ? $app->getRequest() : null;

        return $twig->render($this->template, [
            'locale' => $options['locale'] ?? $request?->getLocale() ?? 'en',
            'chips' => \array_values(\array_filter($options['chips'] ?? [], 'is_string')),
            'js_keys' => [...self::JS_KEYS, ...\array_map(static fn (string $type): string => 'card.type.' . $type, $this->contentTypes)],
        ]);
    }
}
