<?php

declare(strict_types=1);

namespace Laragear\Expose;

use Composer\Composer;
use Composer\Config\JsonConfigSource;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Laragear\Expose\Commands\ConfigureCommand;
use Laragear\Expose\Commands\ExposeCommand;
use Laragear\Expose\Commands\ListTunnelsCommand;
use Laragear\Expose\Commands\StatusCommand;
use Laragear\Expose\Commands\UninstallCommand;
use Laragear\Expose\Commands\UpdateCommand;
use Laragear\Expose\Container\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function app;
use function array_map;
use function get_class;
use function realpath;
use function str_starts_with;

/**
 * Registers the Expose plugin and its command provider into Composer.
 */
class Plugin implements PluginInterface, Capable, EventSubscriberInterface, CommandProvider
{
    /**
     * Activates the plugin.
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $container = Container::getInstance();

        $container->instance(Composer::class, $composer);
        $container->instance(IOInterface::class, $io);

        $container->singleton('projectRoot', static function (): string {
            return realpath(Factory::getComposerFile());
        });

        $container->singleton(JsonFile::class, static function (): JsonFile {
            return new JsonFile(Factory::getComposerFile());
        });

        $container->singleton(JsonConfigSource::class, static function (Container $container): JsonConfigSource {
            return new JsonConfigSource($container->make(JsonFile::class));
        });
    }

    /**
     * Deactivates the plugin.
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        Container::setInstance();
    }

    /**
     * Uninstalls the plugin.
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {

    }

    /**
     * @inheritDoc
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => static::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::COMMAND => 'onComposerCommand',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getCommands(): array
    {
        return array_map(Container::getInstance()->make(...), [
            ExposeCommand::class,
            UpdateCommand::class,
            ConfigureCommand::class,
            StatusCommand::class,
            UninstallCommand::class,
            ListTunnelsCommand::class,
        ]);
    }

    /**
     * Run when the command is executing.
     */
    public function onComposerCommand(CommandEvent $event): void
    {
        if (str_starts_with($event->getCommandName(), 'expose')) {
            $container = Container::getInstance();

            $container->instance(InputInterface::class, $event->getInput());
            $container->alias(get_class($event->getInput()), InputInterface::class);

            $container->instance(OutputInterface::class, $event->getOutput());
            $container->alias(get_class($event->getOutput()), OutputInterface::class);
        }
    }
}
