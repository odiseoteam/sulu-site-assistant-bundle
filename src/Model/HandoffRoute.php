<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** The paths that apply when every condition holds. */
final readonly class HandoffRoute
{
    /**
     * @param array<string, mixed> $when condition name => expected value
     * @param list<string> $paths
     */
    public function __construct(
        public array $when,
        public array $paths,
        public PathChoice $choice = PathChoice::Visitor,
        public ?ContactPolicy $contact = null,
    ) {
    }

    /** @param array{when?: array<string, mixed>, paths: list<string>, choice?: string, contact?: ?string} $route */
    public static function fromArray(array $route): self
    {
        return new self(
            $route['when'] ?? [],
            $route['paths'],
            PathChoice::from($route['choice'] ?? PathChoice::Visitor->value),
            ContactPolicy::tryFrom($route['contact'] ?? ''),
        );
    }
}
