<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Functional;

final class AdminMetadataTest extends AdminTestCase
{
    public function testTheSectionIsInTheAdminWithItsViewsAndResources(): void
    {
        $config = $this->json('GET', '/admin/config');
        $encoded = (string) \json_encode($config);

        foreach (['odiseo_site_assistant.statistics', 'odiseo_site_assistant.handoffs', 'odiseo_site_assistant.rules', 'odiseo_site_assistant.settings', 'site_assistant_handoffs', 'site_assistant_settings'] as $needle) {
            self::assertStringContainsString($needle, $encoded);
        }
    }

    public function testTheListsAndTheSettingsFormHaveMetadata(): void
    {
        foreach (['site_assistant_conversations', 'site_assistant_handoffs', 'site_assistant_rules', 'site_assistant_documents', 'site_assistant_settings'] as $list) {
            $this->json('GET', '/admin/metadata/list/' . $list);
        }

        $form = $this->json('GET', '/admin/metadata/form/site_assistant_settings');
        self::assertStringContainsString('turnModel', (string) \json_encode($form));
    }
}
