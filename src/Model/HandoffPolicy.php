<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/**
 * The site's handoff settings. Topics are shared by every webspace, since they are prompt bytes;
 * a webspace decides which ones it uses, its routes and its hours.
 *
 * @phpstan-type Route array{when?: array<string, mixed>, paths: list<string>, choice?: string, contact?: ?string}
 * @phpstan-type Topic array{description?: ?string, category?: ?string, mode?: ?string, enabled?: bool, routes?: list<Route>}
 * @phpstan-type Webspace array{hours?: array<string, list<string>>, routes?: list<Route>, topics?: array<string, array{enabled?: bool, routes?: list<Route>}>}
 * @phpstan-type Settings array{reference_prefix?: string, hours?: array<string, list<string>>, routes?: list<Route>, topics?: array<string, Topic>, webspaces?: array<string, Webspace>}
 */
final readonly class HandoffPolicy
{
    public const DEFAULT_REFERENCE_PREFIX = 'W-';

    /** The topics every site starts with. A site changes them under the same id. */
    public const BASE_TOPICS = [
        'person' => [
            'description' => 'The visitor asks for a person, a call, a meeting or to be contacted. An explicit request always goes here, even in the middle of another topic.',
            'category' => 'visitor_request',
            'mode' => 'direct',
        ],
        'quote' => [
            'description' => 'The visitor wants a price, a quote, a proposal, availability or a start date, or to hire or book something: only the team can commit to it. Understand first what they need, for what, and any constraint they stated.',
            'category' => 'sale',
            'mode' => 'direct',
        ],
        'support' => [
            'description' => 'Someone already working with the organisation has a problem with that work, a service or an account. Understand what happened first.',
            'category' => 'support',
            'mode' => 'direct',
        ],
        'sensitive' => [
            'description' => 'The visitor mentions a lawyer, a legal claim, a complaint to an authority or a press inquiry.',
            'category' => 'out_of_scope',
            'mode' => 'direct',
        ],
        'unresolved' => [
            'description' => 'You could not solve it: nothing published answers the question, the visitor is upset, or keeps repeating the same thing.',
            'category' => 'knowledge_unavailable',
            'mode' => 'offer',
        ],
    ];

    /**
     * @param array<string, HandoffTopic> $topics
     * @param Settings $settings
     */
    private function __construct(
        public array $topics,
        private array $settings,
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        /** @var Settings $settings */
        $settings = $config;
        $topics = [];
        foreach (self::mergedTopics($settings['topics'] ?? []) as $id => $topic) {
            if (false === ($topic['enabled'] ?? true)) {
                continue;
            }
            if (!isset($topic['description'], $topic['category'], $topic['mode'])) {
                throw new \InvalidArgumentException(\sprintf('The handoff topic "%s" needs a description, a category and a mode.', $id));
            }
            $topics[$id] = new HandoffTopic($id, $topic['description'], HandoffCategory::from($topic['category']), HandoffMode::from($topic['mode']));
        }

        return new self($topics, $settings);
    }

    public function referencePrefix(): string
    {
        return $this->settings['reference_prefix'] ?? self::DEFAULT_REFERENCE_PREFIX;
    }

    public function topic(string $id): ?HandoffTopic
    {
        return $this->topics[$id] ?? null;
    }

    /**
     * The most specific routes set for this topic in this webspace; none means no handoff here.
     *
     * @return list<HandoffRoute>
     */
    public function routes(string $topicId, ?string $webspace): array
    {
        if (!isset($this->topics[$topicId])) {
            return [];
        }

        $inWebspace = null === $webspace ? [] : ($this->settings['webspaces'][$webspace] ?? []);
        $topicInWebspace = $inWebspace['topics'][$topicId] ?? [];
        if (false === ($topicInWebspace['enabled'] ?? true)) {
            return [];
        }

        foreach ([
            $topicInWebspace['routes'] ?? [],
            $inWebspace['routes'] ?? [],
            $this->settings['topics'][$topicId]['routes'] ?? [],
            $this->settings['routes'] ?? [],
        ] as $routes) {
            if ([] !== $routes) {
                return \array_map(HandoffRoute::fromArray(...), $routes);
            }
        }

        return [];
    }

    public function schedule(?string $webspace): HandoffSchedule
    {
        $hours = null === $webspace ? [] : ($this->settings['webspaces'][$webspace]['hours'] ?? []);

        return new HandoffSchedule([] !== $hours ? $hours : ($this->settings['hours'] ?? []));
    }

    /** Without a single route, in any webspace, the assistant has nowhere to hand off. */
    public function hasRoutes(): bool
    {
        foreach ($this->routeSets() as $routes) {
            if ([] !== $routes) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> every path name the settings use */
    public function pathNames(): array
    {
        $names = [];
        foreach ($this->routeSets() as $routes) {
            foreach ($routes as $route) {
                $names = [...$names, ...$route['paths']];
            }
        }

        return \array_values(\array_unique($names));
    }

    /** @return list<string> every condition name the settings use */
    public function conditionNames(): array
    {
        $names = [];
        foreach ($this->routeSets() as $routes) {
            foreach ($routes as $route) {
                $names = [...$names, ...\array_map('strval', \array_keys($route['when'] ?? []))];
            }
        }

        return \array_values(\array_unique($names));
    }

    /**
     * @param array<string, Topic> $configured
     *
     * @return array<string, Topic>
     */
    private static function mergedTopics(array $configured): array
    {
        $topics = self::BASE_TOPICS;
        foreach ($configured as $id => $topic) {
            $topics[$id] = \array_replace($topics[$id] ?? [], \array_filter($topic, static fn (mixed $value): bool => null !== $value));
        }

        return $topics;
    }

    /** @return list<list<Route>> */
    private function routeSets(): array
    {
        $sets = [$this->settings['routes'] ?? []];
        foreach ($this->settings['topics'] ?? [] as $topic) {
            $sets[] = $topic['routes'] ?? [];
        }
        foreach ($this->settings['webspaces'] ?? [] as $webspace) {
            $sets[] = $webspace['routes'] ?? [];
            foreach ($webspace['topics'] ?? [] as $topic) {
                $sets[] = $topic['routes'] ?? [];
            }
        }

        return $sets;
    }
}
