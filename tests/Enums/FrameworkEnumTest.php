<?php

declare(strict_types=1);

namespace Tests\Enums;

use Laragear\Expose\Enums\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Tests the Framework enum's computed properties and helpers. */
class FrameworkEnumTest extends TestCase
{
    /** Returns frameworks that have a native built-in server. */
    public static function frameworksWithBuiltInServer(): array
    {
        return [
            'Laravel'  => [Framework::Laravel],
            'Lumen'    => [Framework::Lumen],
            'Symfony'  => [Framework::Symfony],
        ];
    }

    /** Returns frameworks that rely on PHP's built-in server. */
    public static function frameworksWithoutBuiltInServer(): array
    {
        return [
            'CakePHP'   => [Framework::CakePHP],
            'Yii'       => [Framework::Yii],
            'WordPress' => [Framework::WordPress],
            'Unknown'   => [Framework::Unknown],
        ];
    }

    #[DataProvider('frameworksWithBuiltInServer')]
    public function test_has_built_in_server_returns_true(Framework $framework): void
    {
        static::assertTrue($framework->hasBuiltInServer());
    }

    #[DataProvider('frameworksWithoutBuiltInServer')]
    public function test_has_built_in_server_returns_false(Framework $framework): void
    {
        static::assertFalse($framework->hasBuiltInServer());
    }

    #[DataProvider('frameworksWithBuiltInServer')]
    public function test_server_command_is_non_null_for_native_server_frameworks(Framework $framework): void
    {
        static::assertNotNull($framework->serverCommand());
    }

    #[DataProvider('frameworksWithoutBuiltInServer')]
    public function test_server_command_is_null_for_php_builtin_frameworks(Framework $framework): void
    {
        static::assertNull($framework->serverCommand());
    }

    public function test_laravel_server_command_contains_artisan_serve(): void
    {
        static::assertStringContainsString('artisan serve', Framework::Laravel->serverCommand());
    }

    public function test_symfony_server_command_contains_symfony_server_start(): void
    {
        static::assertStringContainsString('symfony server:start', Framework::Symfony->serverCommand());
    }

    public function test_server_command_contains_host_and_port_placeholders(): void
    {
        foreach ([Framework::Laravel, Framework::Lumen] as $framework) {
            $command = $framework->serverCommand();

            static::assertStringContainsString('{host}', $command);
            static::assertStringContainsString('{port}', $command);
        }
    }

    public function test_public_dir_for_laravel_and_lumen(): void
    {
        static::assertSame('public', Framework::Laravel->publicDir());
        static::assertSame('public', Framework::Lumen->publicDir());
    }

    public function test_public_dir_for_cakephp(): void
    {
        static::assertSame('webroot', Framework::CakePHP->publicDir());
    }

    public function test_public_dir_for_yii(): void
    {
        static::assertSame('web', Framework::Yii->publicDir());
    }

    public function test_public_dir_for_wordpress_and_unknown(): void
    {
        static::assertSame('.', Framework::WordPress->publicDir());
        static::assertSame('.', Framework::Unknown->publicDir());
    }

    public function test_label_returns_value(): void
    {
        static::assertSame('Laravel', Framework::Laravel->label());
        static::assertSame('PHP', Framework::Unknown->label());
    }
}
