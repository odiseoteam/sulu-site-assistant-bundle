<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\DependencyInjection\Compiler;

use Odiseo\SuluSiteAssistantBundle\Model\HandoffPolicy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * A route that names an unknown path or condition fails here, not in the middle of a chat; so
 * does team_contact without a single notice to tell the team.
 */
final class ValidateHandoffRoutesPass implements CompilerPassInterface
{
    public const PATH_TAG = 'odiseo_sulu_site_assistant.handoff_path';

    public const CONDITION_TAG = 'odiseo_sulu_site_assistant.handoff_condition';

    public const NOTICE_TAG = 'odiseo_sulu_site_assistant.handoff_notice';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('odiseo_sulu_site_assistant.handoff')) {
            return;
        }

        /** @var array<string, mixed> $settings */
        $settings = $container->getParameter('odiseo_sulu_site_assistant.handoff');
        $policy = HandoffPolicy::fromArray($settings);

        self::check('path', $policy->pathNames(), self::names($container, self::PATH_TAG, 'path'));
        self::check('condition', $policy->conditionNames(), self::names($container, self::CONDITION_TAG, 'condition'));

        if (\in_array('team_contact', $policy->pathNames(), true) && [] === $container->findTaggedServiceIds(self::NOTICE_TAG)) {
            throw new InvalidArgumentException('The handoff path "team_contact" needs a notice to tell the team: set handoff.notice.email.recipients, or tag a service odiseo_sulu_site_assistant.handoff_notice.');
        }
    }

    /**
     * @param list<string> $used
     * @param list<string> $known
     */
    private static function check(string $kind, array $used, array $known): void
    {
        $unknown = \array_diff($used, $known);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown handoff %s "%s". Known: %s.', $kind, \implode('", "', $unknown), \implode(', ', $known)));
        }
    }

    /** @return list<string> */
    private static function names(ContainerBuilder $container, string $tag, string $attribute): array
    {
        $names = [];
        /** @var array<string, list<array<string, mixed>>> $services */
        $services = $container->findTaggedServiceIds($tag);
        foreach ($services as $tags) {
            foreach ($tags as $attributes) {
                $names[] = \is_string($attributes[$attribute] ?? null) ? $attributes[$attribute] : '';
            }
        }

        return $names;
    }
}
