<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Wallet\AppleWalletConfig;
use App\Services\Wallet\AppleWalletPackageValidator;
use Illuminate\Console\Command;

/** Check a .pkpass file: structure, manifest hashes, signature, required keys and image sizes. */
class ValidateApplePass extends Command
{
    protected $signature = 'wallet:validate {file : Path to a .pkpass file} {--wwdr= : WWDR intermediate certificate (PEM) to check the signer against}';

    protected $description = 'Validate an Apple Wallet .pkpass package';

    public function handle(AppleWalletPackageValidator $validator, AppleWalletConfig $config): int
    {
        $file = (string) $this->argument('file');
        if (!is_file($file)) {
            $this->error("No such file: {$file}");

            return self::FAILURE;
        }
        $result = $validator->validate((string) file_get_contents($file), (string) ($this->option('wwdr') ?: $config->wwdrCertificatePath()));
        if ($result['pass'] !== null) {
            $this->line('Pass: ' . ($result['pass']['description'] ?? '') . ' · serial ' . ($result['pass']['serialNumber'] ?? '?'));
        }
        if (!$result['valid']) {
            foreach ($result['problems'] as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }
        $this->info('Valid .pkpass package.');

        return self::SUCCESS;
    }
}
