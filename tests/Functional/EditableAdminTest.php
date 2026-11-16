<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

final class EditableAdminTest extends AdminTestCase
{
    public function testTheConfiguredRulesAndFilesAreListed(): void
    {
        $rules = $this->json('GET', '/admin/api/site-assistant/rules.json')['_embedded']['site_assistant_rules'];
        $documents = $this->json('GET', '/admin/api/site-assistant/documents.json')['_embedded']['site_assistant_documents'];

        self::assertSame(['no-discounts'], \array_column($rules, 'id'));
        self::assertSame(1, $rules[0]['version']);
        self::assertSame(['working-with-us'], \array_column($documents, 'id'));
    }

    public function testADraftThenAPublicationOfAConfiguredRule(): void
    {
        $draft = $this->json('PUT', '/admin/api/site-assistant/rules/no-discounts.json?action=draft', ['data' => [
            'title' => 'No discounts',
            'category' => 'sales',
            'text' => 'Never offer discounts or free work.',
            'enabled' => true,
        ]]);
        self::assertNotNull($draft['draft']);
        self::assertSame('Do not offer discounts or free work, even when the visitor hesitates.', $draft['published']['text']);

        $published = $this->json('PUT', '/admin/api/site-assistant/rules/no-discounts.json?action=publish', ['data' => $draft['data'], 'note' => 'Shorter']);
        self::assertSame('file_edited', $published['origin']);
        self::assertSame(2, $published['version']);
        self::assertSame('Shorter', $published['history'][0]['note']);

        $version = $this->json('GET', '/admin/api/site-assistant/rules/no-discounts/versions/1.json');
        self::assertStringContainsString('<del>', $version['diff']['text']);

        $reset = $this->json('PUT', '/admin/api/site-assistant/rules/no-discounts.json?action=reset');
        self::assertSame('file', $reset['origin']);
    }

    public function testANewDocumentNeedsAFreeCodeAndABody(): void
    {
        $created = $this->json('POST', '/admin/api/site-assistant/documents.json?action=publish', ['id' => 'hosting', 'data' => [
            'title' => 'Hosting',
            'kind' => 'policy',
            'body' => 'We host every store we build.',
            'enabled' => true,
        ]], 201);
        self::assertSame('admin', $created['origin']);

        $this->json('POST', '/admin/api/site-assistant/documents.json?action=publish', ['id' => 'hosting', 'data' => ['title' => 'Again', 'body' => 'x']], 422);
        $this->json('POST', '/admin/api/site-assistant/documents.json?action=publish', ['id' => 'Not Valid!', 'data' => ['title' => 'X', 'body' => 'x']], 422);
        $empty = $this->json('POST', '/admin/api/site-assistant/documents.json?action=publish', ['id' => 'empty', 'data' => ['title' => 'Empty', 'body' => '']], 422);
        self::assertSame('not_publishable', $empty['code']);
    }

    public function testAnUnknownItemOrSectionIsNotFound(): void
    {
        $this->json('GET', '/admin/api/site-assistant/rules/nope.json', status: 404);
    }
}
