<?php

declare(strict_types=1);

use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    $options = getopt('', ['version:', 'site-folder', 'cpanel']);
    $version = $options['version'] ?? '1.0.0';
    if (!is_string($version) || !preg_match('/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\z/D', $version)) {
        fwrite(STDERR, "Indica una versión semántica con --version=1.0.0.\n");
        return 1;
    }
    if (!class_exists(ZipArchive::class) || !is_file(contactPackage() . '/composer.lock')) {
        fwrite(STDERR, "La compilación requiere ext-zip y composer.lock.\n");
        return 1;
    }
    $root = contactPackage();
    $dist = "$root/dist";
    if (is_link($dist) || (!is_dir($dist) && !mkdir($dist, 0700))) {
        throw new RuntimeException('Invalid dist directory.');
    }
    $siteFolder = isset($options['site-folder']);
    $cpanel = isset($options['cpanel']);
    if ($siteFolder && $cpanel) {
        fwrite(STDERR, "Elige solo un formato de distribución.\n");
        return 1;
    }
    $archiveName = $cpanel ? 'contact-cpanel' : ($siteFolder ? 'contact-folder' : 'php-contact-module');
    $archiveRoot = $cpanel ? 'contact-module' : ($siteFolder ? 'contact' : 'php-contact-module');
    $output = "$dist/$archiveName-v$version.zip";
    if (file_exists($output) || is_link($output)) {
        fwrite(STDERR, "El ZIP de esta versión ya existe.\n");
        return 1;
    }
    $stage = sys_get_temp_dir() . '/pcm-release-' . bin2hex(random_bytes(16));
    if (!mkdir($stage, 0700)) {
        throw new RuntimeException('Cannot create release stage.');
    }
    $entries = [
        '.htaccess', '.env.example', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'CHANGELOG.md',
        'bin/common.php', 'bin/install.php', 'bin/migrate.php', 'bin/retry.php',
        'bin/cleanup.php', 'bin/healthcheck.php', 'bin/admin-password.php',
        'src', 'config/forms/contact.php', 'config/forms/quotation.php', 'config/messages.php',
        'public/contact.php', 'public/config.php', 'public/admin.php', 'public/loader.php', 'public/assets/contact.js',
        'templates/admin.php',
        'templates/emails/notification.php', 'templates/emails/notification-text.php',
        'templates/emails/confirmation.php', 'templates/emails/confirmation-text.php',
        'database/migrations', 'integrations/google-apps-script/Code.gs',
        'integrations/google-apps-script/appsscript.json', 'integrations/google-apps-script/README.md',
        'examples', 'docs',
    ];
    $temporaryZip = $stage . '/release.zip';
    try {
        mkdir($stage . '/package', 0700);
        foreach ($entries as $entry) {
            contactStage("$root/$entry", "$stage/package/$entry");
        }
        $command = 'composer install --no-dev --no-interaction --prefer-dist'
            . ' --optimize-autoloader --no-scripts --no-plugins';
        passthru('cd ' . escapeshellarg($stage . '/package') . ' && ' . $command, $status);
        if ($status !== 0) {
            return 1;
        }
        $base = $stage . '/package';
        if ($cpanel) {
            contactPrepareCpanel($base);
        }
        $zip = new ZipArchive();
        if ($zip->open($temporaryZip, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create archive.');
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        if ($cpanel && !$zip->addEmptyDir($archiveRoot . '/storage/logs')) {
            throw new RuntimeException('Cannot stage log directory.');
        }
        foreach ($files as $file) {
            $path = $file->getPathname();
            $name = substr($path, strlen($base) + 1);
            if (
                $file->isFile() && !$file->isLink() && (($cpanel && $name === '.env') || !preg_match(
                    '~(?:^|/)(?:\.git[^/]*|\.env(?!\.example$)[^/]*|tests?|tmp|\.github)(?:/|$)~i',
                    $name
                ))
            ) {
                if (!$zip->addFile($path, $archiveRoot . '/' . $name)) {
                    throw new RuntimeException('Cannot stage archive member.');
                }
                if ($cpanel && str_starts_with($name, 'public/')) {
                    if (!$zip->addFile($path, 'public_html/contact/' . substr($name, strlen('public/')))) {
                        throw new RuntimeException('Cannot stage public entry.');
                    }
                }
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Cannot finalize archive.');
        }
        if (!@link($temporaryZip, $output)) {
            $source = fopen($temporaryZip, 'rb');
            if ($source === false) {
                throw new RuntimeException('Cannot read archive.');
            }
            try {
                $target = @fopen($output, 'xb');
                if ($target === false) {
                    throw new RuntimeException('Cannot publish archive locally.');
                }
                try {
                    if (stream_copy_to_stream($source, $target) !== filesize($temporaryZip)) {
                        unlink($output);
                        throw new RuntimeException('Incomplete release archive.');
                    }
                } finally {
                    fclose($target);
                }
            } finally {
                fclose($source);
            }
        }
        echo $output, PHP_EOL;
        return 0;
    } finally {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($stage);
    }
});

function contactPrepareCpanel(string $base): void
{
    require $base . '/vendor/autoload.php';
    foreach (['storage/database', 'storage/cache'] as $directory) {
        if (!mkdir($base . '/' . $directory, 0700, true)) {
            throw new RuntimeException('Cannot stage private storage.');
        }
    }
    $settings = <<<'ENV'
        APP_URL=https://tudominio.com
        TURNSTILE_SITE_KEY=
        TURNSTILE_SECRET_KEY=
        ALLOWED_ORIGINS=
        TURNSTILE_ALLOWED_HOSTNAMES=@APP_HOST
        RATE_LIMIT_KEY=@TURNSTILE
        ENV;
    if (file_put_contents($base . '/.env', $settings . PHP_EOL) === false) {
        throw new RuntimeException('Cannot stage configuration template.');
    }
    $migration = new MigrationManager();
    $databases = [
        'storage/database/contacts.sqlite' => ['submissions'],
        'storage/cache/rate.sqlite' => ['rate_limits'],
    ];
    foreach ($databases as $path => $components) {
        $pdo = Connection::sqlite($base . '/' . $path);
        $migration->run($pdo, $base, $components);
        $pdo->query('PRAGMA journal_mode = DELETE')->closeCursor();
        unset($pdo);
    }
}

function contactStage(string $source, string $target): void
{
    if (is_link($source)) {
        throw new RuntimeException('Symlinks are not included in releases.');
    }
    if (is_dir($source)) {
        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $file) {
            if (!$file->isLink() && !str_starts_with($file->getFilename(), '.')) {
                contactStage($file->getPathname(), $target . '/' . $file->getFilename());
            }
        }
        return;
    }
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) {
        throw new RuntimeException('Cannot create release directory.');
    }
    if (!is_file($source) || !copy($source, $target)) {
        throw new RuntimeException('Missing release source.');
    }
}
