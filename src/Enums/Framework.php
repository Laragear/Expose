<?php

declare(strict_types=1);

namespace Laragear\Expose\Enums;

/**
 * Lists the PHP frameworks and platforms this plugin can detect.
 */
enum Framework: string
{
    case Laravel = 'Laravel';
    case Lumen = 'Lumen';
    case Symfony = 'Symfony';
    case CakePHP = 'CakePHP';
    case Yii = 'Yii';
    case WordPress = 'WordPress';
    case Unknown = 'PHP';

    /**
     * Returns the human-readable label for this framework.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Returns the default document root directory for this framework.
     */
    public function publicDir(): string
    {
        return match ($this) {
            self::Laravel, self::Lumen, self::Symfony => 'public',
            self::CakePHP => 'webroot',
            self::Yii => 'web',
            self::WordPress, self::Unknown => '.',
        };
    }

    /**
     * Returns whether the framework supports a built-in CLI dev-server command.
     */
    public function hasBuiltInServer(): bool
    {
        return match ($this) {
            self::Laravel, self::Lumen, self::Yii, self::CakePHP, self::WordPress, self::Symfony => true,
            default => false,
        };
    }

    /**
     * Returns the shell command template to start the built-in dev server, or null.
     */
    public function serverCommand(): ?string
    {
        return match ($this) {
            self::Laravel, self::Lumen => 'php artisan serve --host={host} --port={port}',
            self::Symfony => 'symfony server:start --no-tls --port={port}',
            self::Yii => 'php yii serve --port={port} --host={host}',
            self::CakePHP => 'bin/cake server -p {port} -H {host}',
            self::WordPress => 'wp server --port={port} --host={host}',
            default => null,
        };
    }
}
