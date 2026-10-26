<?php

declare(strict_types=1);

$dir = dirname(__DIR__);
// Skip third-party and generated code: vendor/ alone holds thousands of files
$skipDirectories = ['vendor', 'node_modules', 'storage', 'bootstrap/cache'];
$filter = new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    function (SplFileInfo $file) use ($dir, $skipDirectories): bool {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
        return !$file->isDir() || !in_array($relative, $skipDirectories, true);
    }
);
$iterator = new RecursiveIteratorIterator($filter);
$checked = 0;
$failed = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $output = [];
        $returnVar = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $returnVar);
        $checked++;
        if ($returnVar !== 0) {
            echo "FAILED: {$path}\n" . implode("\n", $output) . "\n";
            $failed++;
        }
    }
}

echo "PHP Lint Complete: {$checked} files checked, {$failed} syntax errors.\n";
if ($failed > 0) {
    exit(1);
}
