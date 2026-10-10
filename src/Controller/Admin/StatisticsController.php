<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Controller\Admin;

use Odiseo\SuluSiteAssistantBundle\Admin\SiteAssistantAdmin;
use Odiseo\SuluSiteAssistantBundle\Statistics\StatisticsProvider;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StatisticsController implements SecuredControllerInterface
{
    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly StatisticsProvider $statistics)
    {
    }

    public function getAction(Request $request): Response
    {
        $days = (int) $request->query->get('days', '30');

        return new JsonResponse($this->statistics->provide(\in_array($days, self::PERIODS, true) ? $days : 30));
    }

    public function getSecurityContext(): string
    {
        return SiteAssistantAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }
}
