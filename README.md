# php-skeleton

PHP project template repository. Adheres to [php-pds/skeleton](https://github.com/php-pds/skeleton)

Usage:

- Update `composer.json`
- Search for every XXX and add your new stuff

Neues Hosting:

1. Subdomain erstellen: <https://my.cyon.ch/domain/subdomain> mit Prefix `existenz_`.
1. Überprüfen, ob SSL-Zertifikate da sind (Scheint neuerding automatisch zu passieren.)
1. `ssh-keygen -t ed25519 -C "XXX.existenz.ch"` -> In neue Datei speichern, keine Passphrase.
1. Private Key in Bitwarden als sichere Notiz unter dem Namen `XXX_SSH_PRIVATE_KEY` speichern.
1. Public Key auf Cyon im `authorize_keys` ablegen: `command="/home/existenz/rrsync /home/existenz/www/existenz_XXX/",no-agent-forwarding,no-port-forwarding,no-pty,no-user-rc,no-X11-forwarding ` plus Key.
1. Keys lokal wieder löschen.
1. `secrethubwarden`

[![Project Status: WIP – Initial development is in progress, but there has not yet been a stable, usable release suitable for the public.](https://www.repostatus.org/badges/latest/wip.svg)](https://www.repostatus.org/) [![PHPUnit tests](https://github.com/cstuder/php-skeleton/actions/workflows/test.yml/badge.svg)](https://github.com/cstuder/php-skeleton/actions/workflows/test.yml)

`LIVE`: <XXX>

`TEST`: <XXX>

## Overview

XXX

## Usage

XXX

## Installation

`composer install`

## Development

Run `composer run serve` and open <http://localhost:8000/>.

## Deployment

Run `composer run deploy-TEST` to deploy the curent brach to `TEST`.

Run `composer run deploy-LIVE` to deploy `main` to `LIVE`.

## License

MIT

## Credits

XXX
