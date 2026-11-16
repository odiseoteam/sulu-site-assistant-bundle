<?php

declare(strict_types=1);

use Odiseo\SuluSiteAssistantBundle\Tests\Application\Kernel;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require \dirname(__DIR__) . '/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

// The test kernels run without debug, so a changed config would not rebuild their container.
(new Filesystem())->remove(\glob(__DIR__ . '/../var/cache/*/test*') ?: []);
(new Dotenv())->bootEnv(__DIR__ . '/Application/.env', 'test');

// The bundle's tables come from its migrations, so a run also proves they apply and match the
// mapping; Sulu's own come from the mapping, as sulu:build does. The unit suite needs no database.
/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
$suites = \implode(' ', $arguments);
if (!\str_contains($suites, 'unit') || \str_contains($suites, 'integration') || \str_contains($suites, 'functional')) {
    $kernel = new Kernel('test', false, SuluKernel::CONTEXT_ADMIN);
    $application = new Application($kernel);
    $application->setAutoExit(false);

    $run = static function (array $command) use ($application): string {
        $output = new BufferedOutput();
        if (0 !== $application->run(new ArrayInput($command), $output)) {
            throw new RuntimeException(\sprintf("%s failed:\n%s", \is_string($command['command']) ? $command['command'] : '', $output->fetch()));
        }

        return $output->fetch();
    };

    $run(['command' => 'doctrine:database:create', '--if-not-exists' => true]);
    $run(['command' => 'doctrine:schema:drop', '--force' => true, '--full-database' => true]);
    $run(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]);
    $pending = $run(['command' => 'doctrine:schema:update', '--dump-sql' => true]);
    if (\str_contains($pending, 'odiseo_site_assistant')) {
        throw new RuntimeException("The migrations do not match the mapping:\n" . $pending);
    }
    $run(['command' => 'doctrine:schema:update', '--force' => true]);
    $run(['command' => 'cache:pool:clear', 'pools' => ['cache.app']]);

    $kernel->shutdown();
}
