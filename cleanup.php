<?php

define('WORKING_DIR', '/srv/wodby');

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

if (empty($argv[1])) {
    echo 'Error. API token have to be specified.', PHP_EOL;
    exit;
}

$token = $argv[1];
$api = new Wodby\Api($token, new GuzzleHttp\Client());
$orgs = $api->organization()->loadAll();
$data = [];

echo 'Fetching instances...', PHP_EOL;

foreach ($orgs as $org) {
    /** @var Wodby\Api\Entity\Organization $org */
    $apps = $api->application()->loadAll($org->getId());

    foreach ($apps as $app) {
        /** @var Wodby\Api\Entity\Application $app */
        $instances = $api->instance()->loadAll($app->getId());

        foreach ($instances as $instance) {
            /** @var Wodby\Api\Entity\Instance $instance */
            $data[] = [
                'id' => $instance->getId(),
                'namespace' => sprintf('%s-%s', $instance->getName(), $app->getId()),
            ];
        }
    }
}

echo sprintf('Fetched %d instances.', count($data)), PHP_EOL;

$fs = new Filesystem();
foreach (['_deleted', 'backups', 'instances', 'srv', 'svc', 'usr'] as $name) {
    if (!$fs->exists(WORKING_DIR . "/$name") && !is_link(WORKING_DIR . "/$name")) {
        $fs->mkdir(WORKING_DIR . "/$name");
    }
}

echo 'Scanning directories...', PHP_EOL;
$dirs = new Finder();
$dirs->directories()
    ->depth('== 0')
    ->in(WORKING_DIR . '/backups')
    ->in(WORKING_DIR . '/instances')
    ->in(WORKING_DIR . '/srv')
    ->in(WORKING_DIR . '/svc')
    ->in(WORKING_DIR . '/usr');

$cleanup = [];

foreach ($dirs as $dir) {
    /** @var SplFileInfo $dir */

    $found = FALSE;

    foreach ($data as $item) {
        if ($dir->getBasename() == $item['id']) {
            $found = TRUE;
            break;
        }

        if ($dir->getBasename() == $item['namespace']) {
            $found = TRUE;
            break;
        }
    }

    if ($found) {
        continue;
    }

    $cleanup[] = [
        'src' => $dir->getPathname(),
        'dest' => WORKING_DIR . '/_deleted/' . substr($dir->getPathname(), strlen(WORKING_DIR) + 1),
    ];
}

if (empty($cleanup)) {
    echo 'Ok. Nothing to cleanup.', PHP_EOL;
    exit;
}

echo 'The following dirs should be cleaned:', PHP_EOL;
foreach ($cleanup as $item) {
    echo ' - ', $item['src'], PHP_EOL;
}

echo PHP_EOL;
$confirm = readline('Are you sure you want to move these into ' . WORKING_DIR . '/_deleted?' . ' (y/N): ');

if (trim(strtolower($confirm)) != 'y') {
    echo 'Canceled.' . PHP_EOL;
    exit;
}

echo PHP_EOL;

foreach ($cleanup as $item) {
    $fs->mkdir($item['dest']);
    try {
        $fs->rename($item['src'], $item['dest'], TRUE);
        echo "Moved {$item['src']} => {$item['dest']}", PHP_EOL;
    } catch (\Throwable $e) {
        echo "Failed to move: {$item['src']}", PHP_EOL;
    }
}

echo 'Done!', PHP_EOL;
