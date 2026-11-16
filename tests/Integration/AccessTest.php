<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\SuluSiteAssistantBundle\Settings\AssistantAccess;
use Symfony\Component\HttpFoundation\RequestStack;

/** Restricted access, for a launch: closed to everyone but the browsers that came with the token. */
final class AccessTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var RequestStack $requests */
        $requests = static::getContainer()->get('request_stack');
        static::getContainer()->set('odiseo_sulu_site_assistant.settings.access', new AssistantAccess($requests, 'secret', true, 'launch-token', 'agent'));
    }

    public function testTheAgentIsClosedWithoutTheGrant(): void
    {
        $this->client->request('POST', '/agent/session', server: ['HTTP_X_AGENT_WEBSPACE' => 'example']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheTokenOpensItForThisBrowser(): void
    {
        $this->client->request('GET', '/agent/showcase?agent=launch-token', server: ['HTTP_X_AGENT_WEBSPACE' => 'example']);
        self::assertResponseRedirects('/agent/showcase');
        self::assertNotNull($this->client->getCookieJar()->get(AssistantAccess::COOKIE, '/'));

        $this->client->request('POST', '/agent/session', server: ['HTTP_X_AGENT_WEBSPACE' => 'example']);
        self::assertResponseStatusCodeSame(201);
    }
}
