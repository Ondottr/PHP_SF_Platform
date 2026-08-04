<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Core;

use PHP_SF\System\Classes\TemplateEngines\TwigTemplateEngine;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigTemplateEngineTest extends TestCase
{
    public function testSupportsOnlyTwigTemplates(): void
    {
        if (false === class_exists(Environment::class)) {
            self::markTestSkipped('Twig is required for this test.');
        }

        $engine = new TwigTemplateEngine(static fn (): Environment => self::fail('Factory must not be called.'));

        self::assertTrue($engine->supports('pages/home.html.twig'));
        self::assertTrue($engine->supports('pages/home.twig'));
        self::assertFalse($engine->supports('pages/home.blade.php'));
        self::assertFalse($engine->supports('App\View\welcome_page'));
    }

    public function testRenderUsesInjectedEnvironmentFactory(): void
    {
        if (false === class_exists(Environment::class)) {
            self::markTestSkipped('Twig is required for this test.');
        }

        $twig = new Environment(
            new ArrayLoader(['greeting.html.twig' => 'Hello {{ name }}!']),
        );

        $engine = new TwigTemplateEngine(static fn (): Environment => $twig);

        self::assertSame('Hello World!', $engine->render('greeting.html.twig', ['name' => 'World']));
    }
}
