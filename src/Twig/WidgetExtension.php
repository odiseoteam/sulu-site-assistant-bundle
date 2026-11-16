<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Twig;

use Odiseo\SuluSiteAssistantBundle\Content\ContentDocument;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use Odiseo\SuluSiteAssistantBundle\Settings\SharedCapGate;
use Odiseo\SuluSiteAssistantBundle\Settings\WebspaceSettings;
use Odiseo\SuluSiteAssistantBundle\Site\CurrentWebspace;
use Odiseo\SuluSiteAssistantBundle\Site\PageUrlResolver;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `{{ site_assistant_widget() }}` renders the widget where a template wants it; optional `chips`
 * replace the starter questions for that page. Nothing is rendered on a webspace whose settings
 * turn the assistant off, nor while a cap every visitor shares (day or month) is reached:
 * `site_assistant_enabled()` tells a template when to show its own fallback. Under restricted
 * access the widget is rendered hidden and shows itself only in a browser that holds the grant,
 * so the page stays cacheable.
 *
 * @phpstan-type ContactLink array{label: string, url: string, detail?: ?string}
 */
final class WidgetExtension extends AbstractExtension
{
    /** The strings the JS reads; the rest are rendered by the template itself. */
    public const JS_KEYS = [
        'card.recommended',
        'card.pros',
        'card.cons',
        'card.best_for',
        'card.sources',
        'card.retry',
        'card.carousel.prev',
        'card.carousel.next',
        'card.comparison.compared_on',
        'card.handoff.title',
        'card.handoff.choose',
        'card.handoff.reference',
        'card.handoff.message',
        'card.handoff.leave_contact',
        'card.handoff.team_open',
        'card.handoff.team_closed',
        'card.handoff.phone',
        'card.handoff.failed',
        'widget.input.placeholder',
        'widget.input.placeholder_busy',
        'widget.expand',
        'widget.collapse',
        'widget.status.thinking',
        'tool.search_content',
        'tool.get_content',
        'tool.search_knowledge',
        'tool.request_handoff',
        'tool.recall_memories',
        'tool.save_memory',
        'tool.load_skill',
        'tool.presenting',
        'error.js.connection_lost',
        'error.js.unavailable',
        'error.js.unanswered',
        'error.closed.text',
        'error.http.too_many_sessions',
        'error.http.too_many_messages',
        'error.http.empty_message',
        'error.http.not_configured',
        'error.http.turn_failed',
        'error.http.budget_exceeded',
        'error.limit.session',
        'error.limit.client',
        'error.limit.day',
        'error.limit.month',
    ];

    /**
     * @param list<string> $contentTypes type names, whose `card.type.*` labels the JS reads
     * @param array<string, ContactLink> $contactLinks by name; a url `page:<uuid>` links that page in the visitor's language
     * @param list<string> $linkHosts
     */
    public function __construct(
        private readonly WebspaceSettings $settings,
        private readonly CurrentWebspace $webspace,
        private readonly Webspaces $webspaces,
        private readonly AssistantAccess $access,
        private readonly SharedCapGate $sharedCap,
        private readonly HandoffPolicy $handoff,
        private readonly TranslatorInterface $translator,
        private readonly ContentIndexInterface $index,
        private readonly PageUrlResolver $urls,
        private readonly string $template,
        private readonly array $contentTypes = [],
        private readonly array $contactLinks = [],
        private readonly array $linkHosts = [],
        private readonly bool $newChatButton = true,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_assistant_enabled', $this->enabled(...)),
            new TwigFunction('site_assistant_widget', $this->render(...), ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]),
            new TwigFunction('site_assistant_contact_links', $this->contactLinks(...)),
        ];
    }

    public function enabled(): bool
    {
        return $this->settings->enabled() && !$this->sharedCap->isReached();
    }

    /**
     * @param array<string, mixed> $context
     * @param array{chips?: list<string>, locale?: string, showcase?: array<mixed>} $options
     */
    public function render(Environment $twig, array $context, array $options = []): string
    {
        $showcase = $options['showcase'] ?? null;
        if (null === $showcase && !$this->enabled()) {
            return '';
        }

        $app = $context['app'] ?? null;
        $request = $app instanceof AppVariable ? $app->getRequest() : null;
        $webspace = $this->webspace->key();
        $locale = $options['locale'] ?? $request?->getLocale() ?? $this->webspaces->defaultLocale($webspace);
        if (!\in_array($locale, $this->webspaces->locales($webspace), true)) {
            $locale = $this->webspaces->defaultLocale($webspace);
        }

        $settings = $this->settings->settings();
        $texts = null === $settings ? [] : $settings->getTexts($locale);
        $chips = \array_values(\array_filter($options['chips'] ?? [], 'is_string'));
        $name = $settings?->getAssistantName();

        return $twig->render($this->template, [
            'locale' => $locale,
            'webspace' => $webspace,
            'chips' => [] !== $chips ? $chips : ($texts['starter_chips'] ?? []),
            'welcome_title' => self::filled($texts['welcome_title'] ?? null),
            'welcome_body' => self::filled($texts['welcome_body'] ?? null),
            'assistant_name' => null === $name || '' === \trim($name) ? null : \mb_strtoupper(\mb_substr($name, 0, 1)) . \mb_substr($name, 1),
            'restricted' => null === $showcase && $this->access->restricted() && !$this->access->isOpen($request),
            'access_cookie' => AssistantAccess::UI_COOKIE,
            'new_chat_button' => $this->newChatButton,
            'contact_links' => $this->contactLinks($locale),
            'link_hosts' => $this->linkHosts,
            'js_keys' => [...self::JS_KEYS, ...\array_map(static fn (string $type): string => 'card.type.' . $type, $this->contentTypes)],
            'handoff_paths' => $this->handoff->pathNames(),
            'showcase' => $showcase,
        ]);
    }

    /**
     * Where the widget sends a person: the configured links with their labels translated and
     * `page:<uuid>` resolved in the visitor's language. A page that is not published is left out.
     *
     * @return array<string, ContactLink>
     */
    public function contactLinks(?string $locale = null): array
    {
        $webspace = $this->webspace->key();
        $locale ??= $this->webspaces->defaultLocale($webspace);

        $links = [];
        foreach ($this->contactLinks as $name => $link) {
            $url = $link['url'];
            if (\str_starts_with($url, 'page:')) {
                $page = $this->index->get(ContentDocument::identifier('pages', \substr($url, 5), $locale));
                if (null === $page) {
                    continue;
                }
                $url = $this->urls->path($page->url, $locale, $page->webspaces[0] ?? $webspace);
            }

            $detail = $link['detail'] ?? null;
            $links[$name] = \array_filter([
                'label' => $this->translator->trans($link['label'], [], 'messages', $locale),
                'url' => $url,
                'detail' => null === $detail ? null : $this->translator->trans($detail, [], 'messages', $locale),
            ], static fn (?string $value): bool => null !== $value);
        }

        return $links;
    }

    private static function filled(mixed $value): ?string
    {
        return \is_string($value) && '' !== \trim($value) ? $value : null;
    }
}
