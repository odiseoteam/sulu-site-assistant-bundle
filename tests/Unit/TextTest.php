<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Tests\Unit;

use Odiseo\SuluSiteAssistantBundle\Content\ContentSection;
use Odiseo\SuluSiteAssistantBundle\Content\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testEditorHtmlKeepsParagraphsApart(): void
    {
        self::assertSame(
            "We build marketplaces.\nMulti-vendor & B2B\nSince 2014",
            Text::fromHtml('<p>We build <strong>marketplaces</strong>.</p><ul><li>Multi-vendor &amp; B2B</li><li>Since&nbsp;2014</li></ul>'),
        );
    }

    public function testTermsFoldAccentsAndDropStopwords(): void
    {
        self::assertSame(['migracion', 'sylius'], Text::terms('¿La migración a Sylius?'));
    }

    public function testBestPassageIsTheSectionSharingMostTerms(): void
    {
        $sections = [
            new ContentSection(null, 'We build Symfony platforms.'),
            new ContentSection('Marketplaces', 'Multi-vendor marketplaces on Sylius, with commissions.'),
        ];

        self::assertSame('Marketplaces: Multi-vendor marketplaces on Sylius, with commissions.', Text::bestPassage($sections, 'sylius marketplace commissions', 400));
        self::assertNull(Text::bestPassage($sections, 'pricing', 400));
    }

    public function testTruncateCutsOnAWord(): void
    {
        self::assertSame('Custom commerce for…', Text::truncate('Custom commerce for marketplaces', 22));
    }
}
