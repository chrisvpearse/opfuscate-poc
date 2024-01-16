<?php

namespace App\Console\Commands\Opfuscate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessSignaledException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class Install extends Command
{
    protected $signature = 'opfuscate:install';

    private $osOptions = [
        'macos' => 'Darwin',
    ];

    private $archOptions = [
        'macos' => [
            'arm64',
            'x86_64',
        ],
    ];

    private $systemIds = [
        'macos' => [
            'arm64' => '876abd454a522e67223b7030d92d6757',
            'x86_64' => '876abd454a522e67223b7030d92d6757',
        ],
    ];

    private $webServerHost = 'localhost';

    private $webServerPort = 8001;

    public function handle()
    {
        $os = Process::run('echo $(uname)');

        if ($os->failed() || ! in_array($os = Str::of($os->output())->trim()->toString(), $this->osOptions)) {
            return error('Could not detect a supported OS');
        }

        $os = array_search($os, $this->osOptions);

        $arch = Process::run('echo $(uname -m)');

        if ($arch->failed() || ! in_array($arch = Str::of($arch->output())->trim()->toString(), $this->archOptions[$os])) {
            return error('Could not detect a supported architecture type');
        }

        info('Successfully determined the OS and architecture type');

        table(
            ['OS', 'Arch'],
            [
                [$os, $arch],
            ]
        );

        $home = Process::run('echo $HOME');

        if ($home->failed()) {
            return error('Could not determine the home directory');
        }

        $home = Str::of($home->output())->trim()->toString();

        $opcacheFileCachePath = implode(DIRECTORY_SEPARATOR, [
            $home,
            $lib = 'Library',
            $appSupport = 'Application Support',
            config('app.name'),
        ]);

        $opcacheFileCachePathWithSystemId = implode(DIRECTORY_SEPARATOR, [
            $opcacheFileCachePath,
            $this->systemIds[$os][$arch],
        ]);

        $libAppSupport = implode(DIRECTORY_SEPARATOR, [$lib, $appSupport]);

        if (! File::exists($opcacheFileCachePathWithSystemId)) {
            warning(sprintf('The "%s" path for %s does not exist',
                $libAppSupport,
                config('app.name')
            ));

            $confirmed = confirm(
                label: 'Would you like to create it now?',
                default: true,
                yes: 'OK',
                no: 'Not Now',
                hint: $opcacheFileCachePathWithSystemId
            );

            if (! $confirmed) {
                return error(sprintf('Cannot continue without the "%s" path', $libAppSupport));
            }

            if (! File::makeDirectory($opcacheFileCachePathWithSystemId, 0755, true)) {
                return error(sprintf('Could not create the "%s" path', $libAppSupport));
            }

            info(sprintf('Successfully created the "%s" path', $libAppSupport));
        }

        $opcacheCompiledFilesPath = implode(DIRECTORY_SEPARATOR, [
            $appBasePath = base_path(),
            Str::of(config('app.name'))->lower(),
            $os,
            $arch,
        ]);

        table(
            [
                ['OPcache Compiled Files Path', $opcacheCompiledFilesPath],
                ['Application Base Path', $appBasePath],
                ['OPcache File Cache Path', $opcacheFileCachePath],
                ['System ID', $this->systemIds[$os][$arch]],
            ]
        );

        $confirmed = confirm(
            label: 'Would you like to copy the OPcache Compiled Files to the OPcache File Cache Path?',
            default: true,
            yes: 'OK',
            no: 'Not Now'
        );

        if (! $confirmed) {
            return error('Cannot continue without copying the OPcache Compiled Files to the OPcache File Cache Path');
        }

        $copied = spin(function () use ($opcacheCompiledFilesPath, $opcacheFileCachePathWithSystemId, $appBasePath) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $opcacheCompiledFilesPath,
                    RecursiveDirectoryIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            usleep(mt_rand(666_666, 999_999));

            foreach ($iterator as $item) {
                if (File::exists($path = implode('', [
                    $opcacheFileCachePathWithSystemId,
                    $appBasePath,
                    Str::replace($opcacheCompiledFilesPath, '', $item->getPathname()),
                ]))) {
                    continue;
                }

                if ($item->isDir()) {
                    if (! File::makeDirectory($path, 0755, true)) {
                        warning(sprintf('Could not create the directory: %s', $path));

                        return false;
                    }
                } elseif ($item->isFile()) {
                    if (! File::copy($item->getPathname(), $path)) {
                        warning(sprintf('Could not copy the file: %s', $item->getPathname()));

                        return false;
                    }
                }
            }

            return true;
        }, 'Copying...');

        if (! $copied) {
            return error('There was an error during the copying process');
        }

        info('Successfully copied the OPcache Compiled Files to the OPcache File Cache Path');

        $bin = implode(DIRECTORY_SEPARATOR, [
            $appBasePath,
            'bin',
            $os,
            $arch,
            'php',
        ]);

        $publicPath = implode(DIRECTORY_SEPARATOR, [
            $appBasePath,
            'public',
        ]);

        $index = implode(DIRECTORY_SEPARATOR, [
            $publicPath,
            'index.php',
        ]);

        $cmd = <<<CMD
{$bin} \
 -d opcache.enable_cli=1 \
 -d opcache.file_cache="{$opcacheFileCachePath}" \
 -d opcache.file_cache_only=1 \
 -d opcache.file_cache_consistency_checks=0 \
 -d opcache.use_cwd=0 \
 -d opcache.validate_timestamps=0 \
 -S {$this->webServerHost}:{$this->webServerPort} \
 -t {$publicPath} \
 {$index}
CMD;

        note($cmd);

        $confirmed = confirm(
            label: 'Would you like to run the above command to launch the built-in web server?',
            default: true,
            yes: 'Yes',
            no: 'No',
        );

        if (! $confirmed) {
            return info(sprintf('OK, goodbye! Thank you for trying %s...', config('app.name')));
        }

        info(sprintf('Web server running on: http://%s:%s', $this->webServerHost, $this->webServerPort));
        warning('Press Ctrl+C to stop the server at any time');

        try {
            Process::run($cmd);
        } catch (ProcessSignaledException) {
            return error(match ($os) {
                'macos' => 'Oops! Please click "Allow Anyway" for "php" under "System Settings" >>> "Privacy & Security", then run this command again'
            });
        }
    }
}
