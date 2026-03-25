<?php

declare(strict_types=1);

namespace Laragear\Expose\Enums;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Tunnels\CloudflareTunnel;
use Laragear\Expose\Tunnels\InstatunnelTunnel;
use Laragear\Expose\Tunnels\LocaltunnelTunnel;
use Laragear\Expose\Tunnels\NgrokTunnel;
use Laragear\Expose\Tunnels\PinggyTunnel;
use Laragear\Expose\Tunnels\ZrokTunnel;

/**
 * Lists the supported tunnel services.
 */
enum TunnelService: string
{
    case Ngrok = 'ngrok';
    case Cloudflare = 'cloudflare';
    case Instatunnel = 'instatunnel';
    case Localtunnel = 'localtunnel';
    case Pinggy = 'pinggy';
    case Zrok = 'zrok';

    /**
     * Returns a new Tunnel implementation instance for this service.
     */
    public function make(): Tunnel
    {
        return match ($this) {
            self::Ngrok => app(NgrokTunnel::class),
            self::Cloudflare => app(CloudflareTunnel::class),
            self::Instatunnel => app(InstatunnelTunnel::class),
            self::Localtunnel => app(LocaltunnelTunnel::class),
            self::Pinggy => app(PinggyTunnel::class),
            self::Zrok => app(ZrokTunnel::class),
        };
    }

    /**
     * Returns a label => value map suitable for SymfonyStyle::choice() prompts.
     *
     * @return array<string, string>
     */
    public static function choiceMap(): array
    {
        return array_column(
            array_map(
                fn(self $case) => ['label' => $case->make()->label(), 'value' => $case->value],
                self::cases(),
            ),
            'value',
            'label',
        );
    }
}
