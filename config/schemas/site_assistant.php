<?php

declare(strict_types=1);

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;

// What the assistant reads: every published page and article, split into sections.
return new Index('site_assistant', [
    'id' => new Field\IdentifierField('id'),
    'resourceKey' => new Field\TextField('resourceKey', searchable: false, filterable: true),
    'resourceId' => new Field\TextField('resourceId', searchable: false),
    'locale' => new Field\TextField('locale', searchable: false, filterable: true),
    'webspaces' => new Field\TextField('webspaces', multiple: true, searchable: false, filterable: true),
    'templateKey' => new Field\TextField('templateKey', searchable: false, filterable: true),
    'title' => new Field\TextField('title'),
    'summary' => new Field\TextField('summary'),
    'content' => new Field\TextField('content', multiple: true),
    'url' => new Field\TextField('url', searchable: false),
    'mediaId' => new Field\IntegerField('mediaId', searchable: false),
    'authoredAt' => new Field\DateTimeField('authoredAt', searchable: false),
    'sections' => new Field\JsonObjectField('sections'),
]);
