<?php

define('WORKING_DIR', '/srv/wodby');

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use \Wodby\Api\Entity;

$client = new GuzzleHttp\Client();
$token = '<API Token>';
$api = new Wodby\Api($token, $client);
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

echo sprintf('Fetched %d instances', count($data)), PHP_EOL;

$fs = new Filesystem();
foreach (['_deleted', 'backups', 'instances', 'srv', 'svc', 'usr'] as $name) {
  if (!$fs->exists(WORKING_DIR . "/$name")) {
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
echo 'Done', PHP_EOL;

$ok_counter = 0;
$deleted_counter = 0;

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
    $ok_counter++;
    echo $dir->getBasename(), ': Ok', PHP_EOL;
    continue;
  }

  $rel_name = substr($dir->getPathname(), strlen(WORKING_DIR) + 1);

  echo "Deleting: $rel_name...", PHP_EOL;
  $fs->mkdir(WORKING_DIR . "/_deleted/$rel_name");
  $fs->rename($dir->getPathname(), WORKING_DIR . "/_deleted/$rel_name", TRUE);
  echo "Moved: " . $dir->getPathname() . ' => ' . WORKING_DIR . "/_deleted/$rel_name", PHP_EOL;
  $deleted_counter++;
  echo "Done", PHP_EOL;

  sleep(1);
}

echo sprintf('Dirs stat. Ok: %d, Deleted: %d', $ok_counter, $deleted_counter), PHP_EOL;
