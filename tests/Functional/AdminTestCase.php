<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** The admin's API, as a signed-in user with every permission. */
abstract class AdminTestCase extends SuluTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createAuthenticatedClient();
        $this->client->disableReboot();
    }

    protected function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function json(string $method, string $url, ?array $body = null, int $status = 200): array
    {
        $this->client->jsonRequest($method, $url, $body ?? []);
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());
        $content = (string) $this->client->getResponse()->getContent();

        /** @var array<string, mixed> */
        return '' === $content ? [] : \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
