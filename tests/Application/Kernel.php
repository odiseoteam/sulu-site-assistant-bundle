<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Application;

use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Odiseo\AiConversationalAgentBundle\Bridge\Symfony\OdiseoAiConversationalAgentBundle;
use Odiseo\SuluSiteAssistantBundle\OdiseoSuluSiteAssistantBundle;
use Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Sulu\Snippet\Infrastructure\Symfony\HttpKernel\SuluSnippetBundle;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

/** A Sulu site with one webspace in two languages, pages and articles, and the assistant. */
class Kernel extends SuluTestKernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();
        yield new SuluSnippetBundle();
        yield new SuluArticleBundle();
        yield new AiBundle();
        yield new OdiseoAiConversationalAgentBundle();
        yield new OdiseoSuluSiteAssistantBundle();

        // A site's website runs its own firewall; Symfony AI's attribute listener needs it.
        if (self::CONTEXT_WEBSITE === $this->getContext()) {
            yield new SecurityBundle();
        }

        if ('test' === $this->getEnvironment()) {
            yield new DAMADoctrineTestBundle();
        }
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/config/config.php');
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2) . '/var/cache/' . $this->getContext() . '/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2) . '/var/log';
    }
}
