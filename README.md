# Expose
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/expose.svg)](https://packagist.org/packages/laragear/expose)
[![Latest stable test run](https://github.com/Laragear/Expose/actions/workflows/php.yml/badge.svg?branch=1.x)](https://github.com/Laragear/Expose/actions/workflows/php.yml)
[![Codecov coverage](https://codecov.io/gh/Laragear/Expose/branch/1.x/graph/badge.svg?token=HIngrvQeOj)](https://codecov.io/gh/Laragear/Expose)
[![CodeClimate Maintainability](https://api.codeclimate.com/v1/badges/{token}/maintainability)](https://codeclimate.com/github/Laragear/Expose/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Expose&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Expose)

Expose your application to the Internet in one command

```shell
composer expose
```

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up to date, and maintainable.

## Requirements

* Linux or macOS (Windows _may_ work)
* PHP 8.3 or later
* Composer **2.6** or higher
* A PHP project with a `composer.json` at the root

## Installation

You can install the package via Composer. 

```bash
composer require global laragear/expose
```

## How does this work?

This composer plugin manages popular tunneling services to expose your application on the Internet. This is great when you want to show your application development to somebody far away, through a simple link, especially when dealing with live-client videocalls or meetings.

## Supported Tunnel Services

| Service                                                                                             | Key           | Distributed as      | Auth required            |
|-----------------------------------------------------------------------------------------------------|---------------|---------------------|--------------------------|
| [ngrok](https://ngrok.com)                                                                          | `ngrok`       | Binary (cURL)       | Optional (free tier)     |
| [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) | `cloudflare`  | Binary (cURL)       | Optional (quick tunnels) |
| [InsTunnel](https://instatunnel.com)                                                                | `instatunnel` | NPM package         | Optional                 |
| [Localtunnel](https://localtunnel.me)                                                               | `localtunnel` | NPM package         | No                       |
| [Pinggy](https://pinggy.io)                                                                         | `pinggy`      | SSH (system binary) | Optional                 |
| [Zrok](https://zrok.io)                                                                             | `zrok`        | Binary (cURL)       | Yes (free account)       |

> [!TIP]
> 
> You can [add your own tunnel services](#adding-additional-tunnels). 

## Usage 

To expose your application to the Internet, simple use `composer expose`:

```shell
composer expose
```

On the first run, the command will:

1. **Detect** your PHP framework (Laravel, Symfony, WordPress, CakePHP, Yii, Lumen, or plain PHP).
2. **Ask** which tunnel service you prefer, then save the choice to `composer.json`.
3. **Check** that the tunnel binary is installed; download it or guide you through manual installation if not.
4. **Run** a local development server on `http://localhost:8080` (or the preferreded by your app).
5. **Start** the tunnel and print the public URL.

The are some options you can use when exposing your application:

| Option     | Short | Description                                   |
|------------|-------|-----------------------------------------------|
| `--host`   |       | Local host to bind to (default: `localhost`)  |
| `--port`   | `-p`  | Local port to bind to (default: `8080`)       |
| `--tunnel` | `-t`  | Override the tunnel service for this run only |

For example, you can use _ngrok_ using an alternative host and port.

```bash
composer expose --port=9000
composer expose --tunnel=cloudflare
```

### Updating tunnels

To update the tunnel library, or only one given tunnel, use the `expose:update` command.

```bash
composer expose:update
composer expose:update --force          # Force re-download
composer expose:update --tunnel=ngrok   # Target a specific service
```

### Configuring tunnels

The `expose:configure` command will walk you through service-specific prompts (auth tokens, subdomains, regions, etc.) to properly configure the tunnel you're using. 

```bash
composer expose:configure
composer expose:configure --reset       # Clear saved tunnel preference
```

> [!TIP]
> 
> Sensitive values like auth tokens are never set in the `composer.json`.

### Checking tunnel status

To display whether the tunnel is running, the current public URL, and the connection count (where available), use the `expose:status` command.

```bash
composer expose:status
```

### Uninstall tunnel binaries

The `expose:uninstall` command will uninstall all tunnel binaries. It does not uninstall the plugin.

```bash
composer expose:uninstall
composer expose:uninstall --purge       # Also remove all Expose config from composer.json
```

## Adding additional tunnels

You may be not happy with the current selection of tunnels. In that case, you can add your own tunnel in your `extra.expose` key in your `composer.json`.

The `tunnel` should state the default tunnel, and the `tunnels` should describe your tunnels labels and class.

```json
{
    "extra": {
        "expose": {
            "tunnel": "my-vpn",
            "tunnels": {
                "my-vpn": {
                    "label": "My Corporate VPN Tunnel",
                    "class": "App\\DevTools\\CorpVpnTunnel"
                }
            }
        }
    }
}
```

The `CorpVpnTunnel` class must implement [`Laragear\Expose\Contracts\Tunnel`](src/Contracts/Tunnel.php) (or extend `AbstractTunnel` which may be easier):


```php
namespace App\DevTools;

use Laragear\Expose\Tunnels\AbstractTunnel;
use Symfony\Component\Process\Process;

class CorpVpnTunnel extends AbstractTunnel
{
    public function name(): string 
    { 
        return 'Corporate VPN';
    }

    public function binary(): string
    {
        return 'vpntunnel';
    }

    public function start(string $host, int $port): Process
    {
        $process = $this->buildProcess([
            $this->binaryCommand(), '--local', "{$host}:{$port}",
        ]);
        
        $process->start();
        
        return $process;
    }
}
```

### Adding a tunnel via package

If you're a package author, you can also make your package a _tunnel-provider_ by offering your own adapter through the `extra.expose-tunnel` key.

```json
{
    "name": "acme/my-tunnel",
    "extra": {
        "expose-tunnel": {
            "key":   "acme",
            "label": "Acme Tunnel (acme.example.com)",
            "class": "Acme\\Tunnel\\AcmeTunnel"
        }
    }
}
```

## Configuration

You can publish the configuration through `vendor:publish`:

```shell
php artisan vendor:publish --provider="Laragear\Expose\ExposeServiceProvider" --tag="config"
```

You should receive a file with an array like this:

```php
return [
    // ...
]
```

## Security

If you discover any security-related issues, issue a [Security Advisory](https://github.com/Laragear/Expose/security/advisories/new).

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at the time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2026 Laravel LLC.
