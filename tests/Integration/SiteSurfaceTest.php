<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Integration;

use Odiseo\AiConversationalAgentBundle\Provider\Fake\FakeProvider;
use Odiseo\SuluSiteAssistantBundle\Entity\Settings;
use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/** What a page shows of the assistant, and what turns it off. */
final class SiteSurfaceTest extends IntegrationTestCase
{
    public function testThePageRendersTheWidgetWithTheSitesTextsAndLinks(): void
    {
        $html = $this->renderWidget('es');

        self::assertStringContainsString('data-controller="site-assistant"', $html);
        self::assertStringContainsString('data-site-assistant-locale-value="es"', $html);
        self::assertStringContainsString('data-site-assistant-webspace-value="example"', $html);
        self::assertStringContainsString('¿En qué te podemos ayudar?', $html);
        self::assertStringContainsString('mailto:hello@example.com', \html_entity_decode($html));
        self::assertStringContainsString('Pasándoselo al equipo', \html_entity_decode($html), 'the widget gets the JS strings in the page language');
        self::assertStringContainsString('Les escribo yo', \html_entity_decode($html), 'and each handoff path brings its own');
        self::assertStringNotContainsString(' hidden data-site-assistant-i18n', $html);
    }

    public function testTheWebspaceSettingsReplaceTheWelcomeAndTheChips(): void
    {
        $settings = $this->settings();
        $settings->setTexts('en', ['welcome_title' => 'Hi from Example', 'starter_chips' => ['What do you build?']]);
        $settings->setAssistantName('odi');
        $this->entityManager()->flush();

        $html = $this->renderWidget('en');

        self::assertStringContainsString('Hi from Example', $html);
        self::assertStringContainsString('What do you build?', $html);
        self::assertStringContainsString('aria-label="Odi"', $html);
    }

    public function testAWebspaceTurnedOffHasNoWidgetAndNoAgent(): void
    {
        $this->settings()->setEnabled(false);
        $this->entityManager()->flush();

        self::assertSame('', \trim($this->renderWidget('en')));

        $this->client->request('POST', '/agent/session', server: ['HTTP_X_AGENT_WEBSPACE' => 'example']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', \json_decode((string) $this->client->getResponse()->getContent(), true)['code'] ?? null, 'the widget reads JSON, never a page');
    }

    public function testTheSettingsChangeTheModelAndTheVoice(): void
    {
        $settings = $this->settings();
        $settings->setBrandName('Odiseo');
        $settings->setBrandVoice('warm and brief');
        $this->entityManager()->flush();

        $this->script(FakeProvider::text('Hola.'));
        $this->chat($this->startSession(), 'Hola');

        $prompt = $this->lastSystemPrompt();
        self::assertStringContainsString('Odiseo', $prompt);
        self::assertStringContainsString('warm and brief', $prompt);
    }

    public function testAnOpenedContactLinkIsRecordedAndToldToTheModel(): void
    {
        $this->script(FakeProvider::text('Hello.'), FakeProvider::text('Glad you wrote.'));
        $sessionId = $this->startSession();
        $this->chat($sessionId, 'Hi');

        $this->client->request('POST', '/agent/contact-link', server: ['HTTP_X_SESSION_ID' => $sessionId, 'CONTENT_TYPE' => 'application/json', 'HTTP_X_AGENT_WEBSPACE' => 'example'], content: '{"link":"whatsapp"}');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('POST', '/agent/contact-link', server: ['HTTP_X_SESSION_ID' => $sessionId, 'CONTENT_TYPE' => 'application/json', 'HTTP_X_AGENT_WEBSPACE' => 'example'], content: '{"link":"carrier_pigeon"}');
        self::assertResponseStatusCodeSame(400);

        self::assertNotNull($this->conversation($sessionId)->getContactOpenedAt());
        $this->chat($sessionId, 'I wrote to you');
        self::assertStringContainsString('opened the whatsapp contact link', (string) \json_encode($this->lastModelRequest()?->messages));
    }

    public function testTheShowcaseRendersEveryCardOverTheSitesPages(): void
    {
        $this->client->request('GET', '/agent/showcase', server: ['HTTP_X_AGENT_WEBSPACE' => 'example']);

        self::assertResponseIsSuccessful();
        $html = \html_entity_decode((string) $this->client->getResponse()->getContent());
        foreach (['present_pages' => 'Pages — shortlist', 'guide' => 'How a project starts', 'handoff' => 'W-7KQ2MX'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringContainsString('Sylius development', $html);
    }

    private function settings(): SettingsInterface
    {
        $settings = new Settings();
        $settings->setWebspaceKey('example');
        $this->entityManager()->persist($settings);

        return $settings;
    }

    private function renderWidget(string $locale): string
    {
        $request = Request::create('/' . $locale);
        $request->setLocale($locale);
        $request->headers->set('X-Agent-Webspace', 'example');
        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        try {
            return $twig->createTemplate('{{ site_assistant_widget() }}')->render($twig->getGlobals());
        } finally {
            $stack->pop();
        }
    }
}
