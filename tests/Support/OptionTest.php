<?php

namespace Tests\Support;

use Laragear\Expose\Support\Option;
use Tests\TestCase;

class OptionTest extends TestCase
{
    public function test_normal_option(): void
    {
        $option = Option::name('label');

        static::assertSame('label', $option->label);
        static::assertNull($option->default);
        static::assertTrue($option->isRequired());
        static::assertFalse($option->isNotRequired());
        static::assertFalse($option->isSecret);

        $option = Option::name('label', 'default');

        static::assertSame('label', $option->label);
        static::assertSame('default', $option->default);
        static::assertTrue($option->isRequired());
        static::assertFalse($option->isNotRequired());
        static::assertFalse($option->isSecret);

        $option = Option::name('label')->optional();

        static::assertSame('label', $option->label);
        static::assertNull($option->default);
        static::assertFalse($option->isRequired());
        static::assertTrue($option->isNotRequired());
        static::assertFalse($option->isSecret);
    }

    public function test_secret_option(): void
    {
        $option = Option::secret('label');

        static::assertSame('label', $option->label);
        static::assertNull($option->default);
        static::assertTrue($option->isRequired());
        static::assertFalse($option->isNotRequired());
        static::assertTrue($option->isSecret);

        $option = Option::secret('label', 'default');

        static::assertSame('label', $option->label);
        static::assertSame('default', $option->default);
        static::assertTrue($option->isRequired());
        static::assertFalse($option->isNotRequired());
        static::assertTrue($option->isSecret);

        $option = Option::secret('label')->optional();

        static::assertSame('label', $option->label);
        static::assertNull($option->default);
        static::assertFalse($option->isRequired());
        static::assertTrue($option->isNotRequired());
        static::assertTrue($option->isSecret);
    }

}
