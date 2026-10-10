<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle;

use Odiseo\SuluSiteAssistantBundle\DependencyInjection\OdiseoSuluSiteAssistantExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class OdiseoSuluSiteAssistantBundle extends Bundle
{
    /** The package root, so `templates/`, `translations/` and `public/` resolve there. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new OdiseoSuluSiteAssistantExtension();
    }
}
