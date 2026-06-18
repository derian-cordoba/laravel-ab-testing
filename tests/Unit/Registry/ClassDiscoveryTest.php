<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Registry;

use ABTests\Application\Registry\ClassDiscovery;
use ABTests\Tests\Fixtures\Discovery\DiscoverableExperiment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassDiscoveryTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../../Fixtures/Discovery';
    }

    #[Test]
    public function discovers_class_from_fixture_directory(): void
    {
        $found = (new ClassDiscovery())->discover([$this->fixtureDir]);

        self::assertContains(DiscoverableExperiment::class, $found);
    }

    #[Test]
    public function returns_empty_array_for_missing_directory(): void
    {
        $found = (new ClassDiscovery())->discover(['/nonexistent/path/that/does/not/exist']);

        self::assertSame([], $found);
    }

    #[Test]
    public function ignores_non_php_files(): void
    {
        $tmpDir = sys_get_temp_dir().'/ab-discovery-'.uniqid('', true);
        mkdir($tmpDir);

        file_put_contents($tmpDir.'/readme.md', '# Not PHP');
        file_put_contents($tmpDir.'/notes.txt', 'text file');

        $found = (new ClassDiscovery())->discover([$tmpDir]);

        self::assertSame([], $found);

        unlink($tmpDir.'/readme.md');
        unlink($tmpDir.'/notes.txt');
        rmdir($tmpDir);
    }

    #[Test]
    public function correctly_extracts_namespaced_class_name(): void
    {
        $tmpDir = sys_get_temp_dir().'/ab-discovery-'.uniqid('', true);
        mkdir($tmpDir);

        file_put_contents($tmpDir.'/MyClass.php', '<?php
namespace App\Experiments;
final class MyClass {}
');

        $found = (new ClassDiscovery())->discover([$tmpDir]);

        self::assertContains('App\\Experiments\\MyClass', $found);

        unlink($tmpDir.'/MyClass.php');
        rmdir($tmpDir);
    }

    #[Test]
    public function correctly_extracts_enum_class_name(): void
    {
        $tmpDir = sys_get_temp_dir().'/ab-discovery-'.uniqid('', true);
        mkdir($tmpDir);

        file_put_contents($tmpDir.'/MyEnum.php', '<?php
namespace App\Enums;
enum MyEnum: string { case a = "a"; }
');

        $found = (new ClassDiscovery())->discover([$tmpDir]);

        self::assertContains('App\\Enums\\MyEnum', $found);

        unlink($tmpDir.'/MyEnum.php');
        rmdir($tmpDir);
    }

    #[Test]
    public function handles_file_without_namespace(): void
    {
        $tmpDir = sys_get_temp_dir().'/ab-discovery-'.uniqid('', true);
        mkdir($tmpDir);

        file_put_contents($tmpDir.'/Bare.php', '<?php
class BareClass {}
');

        $found = (new ClassDiscovery())->discover([$tmpDir]);

        self::assertContains('BareClass', $found);

        unlink($tmpDir.'/Bare.php');
        rmdir($tmpDir);
    }

    #[Test]
    public function scans_multiple_directories(): void
    {
        $tmp1 = sys_get_temp_dir().'/ab-disc-a-'.uniqid('', true);
        $tmp2 = sys_get_temp_dir().'/ab-disc-b-'.uniqid('', true);
        mkdir($tmp1);
        mkdir($tmp2);

        file_put_contents($tmp1.'/ClassA.php', '<?php namespace Test; class ClassA {}');
        file_put_contents($tmp2.'/ClassB.php', '<?php namespace Test; class ClassB {}');

        $found = (new ClassDiscovery())->discover([$tmp1, $tmp2]);

        self::assertContains('Test\\ClassA', $found);
        self::assertContains('Test\\ClassB', $found);

        unlink($tmp1.'/ClassA.php');
        unlink($tmp2.'/ClassB.php');
        rmdir($tmp1);
        rmdir($tmp2);
    }
}
