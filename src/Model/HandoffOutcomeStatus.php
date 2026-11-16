<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

enum HandoffOutcomeStatus: string
{
    /** Saved and on its way. Only this one lets the assistant say so. */
    case Registered = 'registered';

    /** Saved; the visitor picks how to go on. */
    case ChoosePath = 'choose_path';

    /** Not saved: a path needs a contact the visitor has not given. */
    case NeedsContact = 'needs_contact';

    /** This webspace has no route for the topic right now. */
    case Unavailable = 'unavailable';

    /** Saved, but the path failed. */
    case Failed = 'failed';
}
