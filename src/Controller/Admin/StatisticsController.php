<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsPeriod;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsProvider;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** A period's numbers: `?period=7d|30d|this_month|last_month` or `?from=&to=`, and `?webspace=`. */
final class StatisticsController implements SecuredControllerInterface
{
    public function __construct(
        private readonly StatisticsProvider $statistics,
        private readonly Webspaces $webspaces,
        private readonly ?string $timezone = null,
    ) {
    }

    public function getAction(Request $request): Response
    {
        $today = new \DateTimeImmutable('today', null === $this->timezone ? null : new \DateTimeZone($this->timezone));
        $period = StatisticsPeriod::fromQuery(
            self::query($request, 'period'),
            self::query($request, 'from'),
            self::query($request, 'to'),
            $today,
        );
        $webspace = self::query($request, 'webspace');
        $webspace = $this->webspaces->has($webspace) ? $webspace : null;

        return new JsonResponse([
            'period' => [
                'preset' => $period->preset,
                'from' => $period->from->format('Y-m-d'),
                'to' => $period->to->format('Y-m-d'),
                'presets' => StatisticsPeriod::PRESETS,
            ],
            'webspace' => $webspace,
            'webspaces' => \array_map(fn (string $key): array => ['key' => $key, 'name' => $this->webspaces->name($key)], $this->webspaces->keys()),
        ] + $this->statistics->provide($period, $webspace)->toArray());
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::CONVERSATIONS_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
