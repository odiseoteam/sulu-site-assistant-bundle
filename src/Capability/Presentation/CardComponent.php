<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Capability\Presentation;

use Odiseo\AiConversationalAgentBundle\Capability\ToolSpec;
use Odiseo\AiConversationalAgentBundle\Presentation\PresentationComponent;

/**
 * One card the model can show: its tool and how the host validates, enriches and streams it.
 *
 * A partial frame is the final card's shape with what is written so far. Only ids the session
 * saw get in; unknown or half-written ids are skipped silently. Nothing is rejected there: the
 * full call's validation reports an empty result.
 */
interface CardComponent
{
    public function tool(): ToolSpec;

    public function component(): PresentationComponent;
}
