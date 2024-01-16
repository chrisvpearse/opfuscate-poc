# OPfuscate — PoC

This GitHub repo contains the following proof of concept:

1. Using a static PHP CLI binary, it is possible to compile your PHP files from `.php` to `.php.bin` with OPcache on any machine which matches your target OS (e.g. macOS) and architecture (e.g. arm64)
2. You are then able to zero out the `.php` files containing your PHP source code
3. Your app should then be packaged for your target OS and architecture, containing the `.php.bin` files, the empty `.php` files and the static PHP CLI binary used in step #1
4. Upon the app's first boot on the end-user's machine, a command should be run to distribute the `.php.bin` files according to the end-user's unique and absolute paths (you cannot use relative paths with OPcache)

## Requirements

1. macOS (arm64 or x86_64)
2. Git
3. Composer
4. PHP ^8.1

## Installation

```console
$ git clone https://github.com/chrisvpearse/opfuscate-poc.git
$ cd ./opfuscate-poc
$ composer install
$ cp .env.example .env
$ php artisan key:generate
```

## Usage

The default web route is the empty `./app/Http/Controllers/HelloWorld.php` controller, however, when you launch the web server, you will see a welcome message which is being returned from `HelloWorld.php.bin` in OPcache's file cache :tada:

```console
$ php artisan opfuscate:install
```

## Credits

* [Christopher Pearse](https://x.com/chrisvpearse)
