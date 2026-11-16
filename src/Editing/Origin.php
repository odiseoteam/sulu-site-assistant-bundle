<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Editing;

/** Where the version in use comes from. */
enum Origin: string
{
    /** The configuration, never edited (or back to it). */
    case File = 'file';

    /** The configuration, replaced by a version published in the admin. */
    case FileEdited = 'file_edited';

    /** Only in the admin. */
    case Admin = 'admin';
}
