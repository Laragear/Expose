<?php

declare(strict_types=1);

namespace Tests\Enums;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\TunnelService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Laragear\Expose\Tunnels\CloudflareTunnel;
use Laragear\Expose\Tunnels\InstatunnelTunnel;
use Laragear\Expose\Tunnels\LocaltunnelTunnel;
use Laragear\Expose\Tunnels\NgrokTunnel;
use Laragear\Expose\Tunnels\PinggyTunnel;
use Laragear\Expose\Tunnels\ZrokTunnel;

/** Tests the TunnelService enum factory, labels, and parsing helpers. */
class TunnelServiceEnumTest extends TestCase
{
    /** Returns all services with their expected Tunnel implementation class. */
    public static function serviceImplementationProvider(): array
    {
        return [
            'ngrok'       => [TunnelService::Ngrok, NgrokTunnel::class],
            'cloudflare'  => [TunnelService::Cloudflare, CloudflareTunnel::class],
            'instatunnel' => [TunnelService::Instatunnel, InstatunnelTunnel::class],
            'localtunnel' => [TunnelService::Localtunnel, LocaltunnelTunnel::class],
            'pinggy'      => [TunnelService::Pinggy, PinggyTunnel::class],
            'zrok'        => [TunnelService::Zrok, ZrokTunnel::class],
        ];
    }

    #[DataProvider('serviceImplementationProvider')]
    public function test_make_returns_correct_tunnel_implementation(
        TunnelService $service,
        string $expectedClass,
    ): void {
        $tunnel = $service->make();

        static::assertInstanceOf(Tunnel::class, $tunnel);
        static::assertInstanceOf($expectedClass, $tunnel);
    }

    public function test_from_string_or_null_returns_case_for_known_value(): void
    {
        static::assertSame(TunnelService::Ngrok, TunnelService::fromStringOrNull('ngrok'));
        static::assertSame(TunnelService::Zrok, TunnelService::fromStringOrNull('zrok'));
    }

    public function test_from_string_or_null_returns_null_for_unknown_value(): void
    {
        static::assertNull(TunnelService::fromStringOrNull('unknown-service'));
        static::assertNull(TunnelService::fromStringOrNull(''));
    }

    public function test_choice_map_contains_all_services(): void
    {
        $map = TunnelService::choiceMap();

        foreach (TunnelService::cases() as $case) {
            static::assertContains($case->value, $map);
        }
    }

    public function test_choice_map_has_labels_as_keys(): void
    {
        $map = TunnelService::choiceMap();

        foreach (TunnelService::cases() as $case) {
            static::assertArrayHasKey($case->make()->label(), $map);
        }
    }

    public function test_npm_based_services_report_as_npm_packages(): void
    {
        static::assertTrue(TunnelService::Localtunnel->make()->isInstallableViaNpm());
        static::assertTrue(TunnelService::Instatunnel->make()->isInstallableViaNpm());
    }

    public function test_binary_based_services_do_not_report_as_npm(): void
    {
        static::assertFalse(TunnelService::Ngrok->make()->isInstallableViaNpm());
        static::assertFalse(TunnelService::Cloudflare->make()->isInstallableViaNpm());
        static::assertFalse(TunnelService::Zrok->make()->isInstallableViaNpm());
    }

    public function test_pinggy_is_not_an_npm_package(): void
    {
        static::assertFalse(TunnelService::Pinggy->make()->isInstallableViaNpm());
    }

    public function test_all_tunnel_names_are_non_empty(): void
    {
        foreach (TunnelService::cases() as $case) {
            static::assertNotEmpty($case->make()->name());
        }
    }

    public function test_all_tunnel_binaries_are_non_empty(): void
    {
        foreach (TunnelService::cases() as $case) {
            static::assertNotEmpty($case->make()->binary());
        }
    }
}
