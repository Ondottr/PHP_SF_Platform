<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Core;

use PHP_SF\System\Classes\TemplateEngines\BladeOneTemplateEngine;
use PHP_SF\System\Classes\TemplateEngines\TwigTemplateEngine;
use PHP_SF\System\Core\TemplateEngineRegistry;
use PHP_SF\Tests\System\Core\Fixtures\StubTemplateEngine;
use PHPUnit\Framework\TestCase;

final class TemplateEngineRegistryTest extends TestCase
{
    public function testResolveReturnsNullForClassViews(): void
    {
        self::assertNull(TemplateEngineRegistry::resolve('App\View\welcome_page'));
    }

    public function testResolveReturnsFirstEngineSupportingTemplate(): void
    {
        $first = new StubTemplateEngine('.custom');
        $second = new StubTemplateEngine('.custom');

        TemplateEngineRegistry::add($first);
        TemplateEngineRegistry::add($second);

        self::assertSame($first, TemplateEngineRegistry::resolve('pages/home.custom'));
        self::assertNull(TemplateEngineRegistry::resolve('pages/home.other'));
    }

    public function testTemplateCssClassStripsDirectoryAndEngineExtension(): void
    {
        self::assertSame('dashboard', TemplateEngineRegistry::templateCssClass('pages/dashboard.html.twig'));
        self::assertSame('dashboard', TemplateEngineRegistry::templateCssClass('pages/dashboard.twig'));
        self::assertSame('dashboard', TemplateEngineRegistry::templateCssClass('pages/dashboard.blade.php'));
        self::assertSame('base', TemplateEngineRegistry::templateCssClass('base.html.twig'));
    }

    public function testBuiltInTwigEngineRegisteredWhenAvailable(): void
    {
        if (false === class_exists(\Twig\Environment::class) || false === class_exists(\App\Kernel::class)) {
            self::markTestSkipped('Twig and the application kernel are required for this test.');
        }

        self::assertInstanceOf(
            TwigTemplateEngine::class,
            TemplateEngineRegistry::resolve('pages/home.html.twig'),
        );
    }

    public function testBuiltInBladeEngineRegisteredWhenAvailable(): void
    {
        if (false === class_exists(\eftec\bladeone\BladeOne::class)) {
            self::markTestSkipped('BladeOne is required for this test.');
        }

        self::assertInstanceOf(
            BladeOneTemplateEngine::class,
            TemplateEngineRegistry::resolve('pages/home.blade.php'),
        );
    }

    protected function setUp(): void
    {
        TemplateEngineRegistry::reset();
    }

    protected function tearDown(): void
    {
        TemplateEngineRegistry::reset();
    }
}
