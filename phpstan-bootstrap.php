<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap for the framework repo.
 *
 * The framework treats Symfony and Doctrine as soft dependencies that the
 * consuming application provides, so they are absent from this package's own
 * vendor/. To analyse src/ + app/ standalone we borrow those classes, the
 * application constants and the debug helpers from the parent template.
 *
 * The framework's own classes are always reflected from the analysed src/ +
 * app/ paths; the autoloader registered here only fills the gaps. Composer's
 * autoload_files are intentionally not run — only the pieces we need are.
 */

$vendorDir = __DIR__ . '/../vendor';

// Composer\Autoload\ClassLoader is already declared by this package's own
// vendor/autoload.php (loaded by PHPStan at startup); reuse it.
if (false === class_exists(Composer\Autoload\ClassLoader::class, false)) {
    require_once $vendorDir . '/composer/ClassLoader.php';
}

$loader = new Composer\Autoload\ClassLoader($vendorDir);

/** @var array<string, string> $namespaces */
$namespaces = require $vendorDir . '/composer/autoload_namespaces.php';
foreach ($namespaces as $namespace => $path) {
    $loader->set($namespace, $path);
}

/** @var array<string, array<int, string>> $psr4 */
$psr4 = require $vendorDir . '/composer/autoload_psr4.php';
foreach ($psr4 as $namespace => $paths) {
    $loader->setPsr4($namespace, $paths);
}

/** @var array<string, string> $classMap */
$classMap = require $vendorDir . '/composer/autoload_classmap.php';
if ([] !== $classMap) {
    $loader->addClassMap($classMap);
}

// Append (not prepend) so this package's own vendor/ wins for its real deps and
// the parent only supplies the soft dependencies.
$loader->register();

// Application constants (DEV_MODE, APPLICATION_NAME, ...) the framework reads
// from the consuming app's generated config.
$constants = __DIR__ . '/../config/constants.php';
if (is_file($constants)) {
    require_once $constants;
}

// Constants the framework reads but expects the consuming application to
// provide; supply neutral fallbacks so reflection during analysis succeeds.
defined('DEFAULT_LOCALE') || define('DEFAULT_LOCALE', 'en');

// IDE attribute classes (jetbrains/phpstorm-attributes) the framework annotates
// with but does not require at runtime.
require_once __DIR__ . '/phpstan-stubs/jetbrains-attributes.php';

// Symfony VarDumper's dump()/dd() global helpers (registered via composer
// autoload_files, which we skipped above).
$dump = $vendorDir . '/symfony/var-dumper/Resources/functions/dump.php';
if (is_file($dump)) {
    require_once $dump;
}
