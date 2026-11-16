<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * Shadow dependencies are ignored on purpose: like other Sulu bundles, this one requires
 * `sulu/sulu` as an umbrella and uses the components it pulls in. DoctrineBundle is only
 * referenced by service id and in the mappings the extension prepends.
 */
return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests/Application/var')
    ->ignoreErrors([ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackage('doctrine/doctrine-bundle', [ErrorType::UNUSED_DEPENDENCY]);
