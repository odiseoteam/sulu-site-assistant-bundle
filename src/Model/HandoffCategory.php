<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** Why a handoff happened, for the reports. */
enum HandoffCategory: string
{
    case VisitorRequest = 'visitor_request';
    case Sale = 'sale';
    case Support = 'support';
    case OutOfScope = 'out_of_scope';
    case KnowledgeUnavailable = 'knowledge_unavailable';
    case ToolFailed = 'tool_failed';
    case Other = 'other';
}
