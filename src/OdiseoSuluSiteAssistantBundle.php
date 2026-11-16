<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle;

use Odiseo\SuluSiteAssistantBundle\DependencyInjection\Compiler\ValidateHandoffRoutesPass;
use Odiseo\SuluSiteAssistantBundle\DependencyInjection\OdiseoSuluSiteAssistantExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class OdiseoSuluSiteAssistantBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ValidateHandoffRoutesPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new OdiseoSuluSiteAssistantExtension();
    }
}
