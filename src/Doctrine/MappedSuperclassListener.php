<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * The bundle maps its models as mapped superclasses so a site can extend them. Whichever class
 * the configuration names becomes the entity: the bundle's own by default, or the site's subclass.
 */
final class MappedSuperclassListener
{
    /** @param list<class-string> $models */
    public function __construct(private readonly array $models)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        if ($metadata->isMappedSuperclass && \in_array($metadata->getName(), $this->models, true)) {
            $metadata->isMappedSuperclass = false;
        }
    }
}
