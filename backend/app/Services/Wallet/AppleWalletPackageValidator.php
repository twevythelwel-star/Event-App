<?php

declare(strict_types=1);

namespace App\Services\Wallet;

/**
 * Checks a .pkpass package the way Wallet will before accepting it: a zip with pass.json, manifest.json and a
 * signature; every file listed in the manifest with a matching SHA-1 and nothing unlisted; a valid detached PKCS #7
 * signature over the manifest made by a certificate issued by the given WWDR intermediate; required pass.json keys;
 * and images within Apple's sizes. Used by `php artisan wallet:validate` and the tests.
 */
final class AppleWalletPackageValidator
{
    private const REQUIRED_KEYS = ['formatVersion', 'passTypeIdentifier', 'serialNumber', 'teamIdentifier', 'organizationName', 'description'];

    private const STYLES = ['boardingPass', 'coupon', 'eventTicket', 'generic', 'storeCard'];

    private const BARCODE_FORMATS = ['PKBarcodeFormatQR', 'PKBarcodeFormatPDF417', 'PKBarcodeFormatAztec', 'PKBarcodeFormatCode128'];

    /** Largest allowed size at 1x; @2x and @3x scale it. */
    private const IMAGE_LIMITS = ['icon' => [29, 29], 'logo' => [160, 50], 'strip' => [375, 98], 'thumbnail' => [90, 90], 'background' => [180, 220]];

    /**
     * @return array{valid: bool, problems: list<string>, pass: array<string, mixed>|null, signer: array<string, mixed>|null}
     */
    public function validate(string $pkpass, string $wwdrCertificatePath): array
    {
        $problems = [];
        $tmp = tempnam(sys_get_temp_dir(), 'pkv');
        file_put_contents($tmp, $pkpass);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return ['valid' => false, 'problems' => ['Not a zip archive.'], 'pass' => null, 'signer' => null];
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $problems[] = "Unsafe path in package: {$name}";

                continue;
            }
            $files[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        foreach (['pass.json', 'manifest.json', 'signature'] as $required) {
            if (!isset($files[$required])) {
                $problems[] = "Missing {$required}.";
            }
        }

        // Manifest: every file but the manifest and signature, each with its SHA-1.
        $manifest = isset($files['manifest.json']) ? json_decode($files['manifest.json'], true) : null;
        if (!is_array($manifest)) {
            $problems[] = 'manifest.json is not valid JSON.';
            $manifest = [];
        }
        foreach ($files as $name => $bytes) {
            if (in_array($name, ['manifest.json', 'signature'], true)) {
                continue;
            }
            if (!isset($manifest[$name])) {
                $problems[] = "{$name} is not listed in the manifest.";
            } elseif (!hash_equals((string) $manifest[$name], sha1($bytes))) {
                $problems[] = "{$name} does not match its manifest hash.";
            }
        }
        foreach (array_keys($manifest) as $listed) {
            if (!isset($files[$listed])) {
                $problems[] = "The manifest lists {$listed}, which is not in the package.";
            }
        }

        $signer = isset($files['signature'], $files['manifest.json'])
            ? $this->verifySignature($files['manifest.json'], $files['signature'], $wwdrCertificatePath, $problems)
            : null;

        $pass = isset($files['pass.json']) ? json_decode($files['pass.json'], true) : null;
        if (!is_array($pass)) {
            $problems[] = 'pass.json is not valid JSON.';
            $pass = null;
        } else {
            $this->checkPass($pass, $signer, $problems);
        }

        $this->checkImages($files, $problems);

        return ['valid' => $problems === [], 'problems' => $problems, 'pass' => $pass, 'signer' => $signer];
    }

    /** @param list<string> $problems @return array<string, mixed>|null the signer's subject */
    private function verifySignature(string $manifest, string $signature, string $wwdrPath, array &$problems): ?array
    {
        $dir = sys_get_temp_dir();
        $manifestFile = tempnam($dir, 'pkm');
        $signatureFile = tempnam($dir, 'pks');
        $signerFile = tempnam($dir, 'pkc');
        file_put_contents($manifestFile, $manifest);
        file_put_contents($signatureFile, $signature);

        try {
            // The signature over the manifest itself. The chain is checked below against the WWDR intermediate,
            // since Apple's root is not on the server.
            $ok = openssl_cms_verify($manifestFile, OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED | OPENSSL_CMS_NOVERIFY,
                $signerFile, [], null, null, null, $signatureFile, OPENSSL_ENCODING_DER);
            while (openssl_error_string() !== false) {
            }
            if ($ok !== true) {
                $problems[] = 'The signature does not verify against manifest.json.';

                return null;
            }
            $signerPem = (string) file_get_contents($signerFile);
            $info = openssl_x509_parse($signerPem) ?: [];
            $wwdr = is_file($wwdrPath) ? (string) file_get_contents($wwdrPath) : '';
            if ($wwdr === '') {
                $problems[] = 'The WWDR intermediate certificate was not found.';
            } else {
                $wwdrKey = openssl_pkey_get_public($wwdr);
                if ($wwdrKey === false || openssl_x509_verify($signerPem, $wwdrKey) !== 1) {
                    $problems[] = 'The signing certificate was not issued by the WWDR intermediate.';
                }
            }
            if (($info['validTo_time_t'] ?? 0) < time()) {
                $problems[] = 'The signing certificate has expired.';
            }

            return $info['subject'] ?? [];
        } finally {
            @unlink($manifestFile);
            @unlink($signatureFile);
            @unlink($signerFile);
        }
    }

    /** @param list<string> $problems */
    private function checkPass(array $pass, ?array $signer, array &$problems): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($pass[$key]) || $pass[$key] === '') {
                $problems[] = "pass.json is missing {$key}.";
            }
        }
        if (($pass['formatVersion'] ?? null) !== 1) {
            $problems[] = 'formatVersion must be 1.';
        }
        $styles = array_values(array_intersect(self::STYLES, array_keys($pass)));
        if (count($styles) !== 1) {
            $problems[] = 'pass.json must have exactly one pass style.';
        }
        if ($signer !== null) {
            if (isset($signer['UID']) && $signer['UID'] !== ($pass['passTypeIdentifier'] ?? null)) {
                $problems[] = 'passTypeIdentifier does not match the signing certificate.';
            }
            if (isset($signer['OU']) && $signer['OU'] !== ($pass['teamIdentifier'] ?? null)) {
                $problems[] = 'teamIdentifier does not match the signing certificate.';
            }
        }
        if (isset($pass['webServiceURL'])) {
            if (!str_starts_with((string) $pass['webServiceURL'], 'https://')) {
                $problems[] = 'webServiceURL must use HTTPS.';
            }
            if (strlen((string) ($pass['authenticationToken'] ?? '')) < 16) {
                $problems[] = 'authenticationToken must be at least 16 characters.';
            }
        }
        foreach ((array) ($pass['barcodes'] ?? []) as $barcode) {
            if (!in_array($barcode['format'] ?? null, self::BARCODE_FORMATS, true) || ($barcode['message'] ?? '') === '' || empty($barcode['messageEncoding'])) {
                $problems[] = 'A barcode needs a supported format, a message and a messageEncoding.';
            }
        }
        foreach (['foregroundColor', 'backgroundColor', 'labelColor'] as $color) {
            if (isset($pass[$color]) && !preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/', (string) $pass[$color])) {
                $problems[] = "{$color} must be rgb(r, g, b).";
            }
        }
        $keys = [];
        foreach ($styles !== [] ? (array) $pass[$styles[0]] : [] as $fields) {
            foreach (is_array($fields) ? $fields : [] as $field) {
                if (!is_array($field)) {
                    continue;
                }
                if (!isset($field['key'], $field['value'])) {
                    $problems[] = 'Every field needs a key and a value.';
                } elseif (isset($keys[$field['key']])) {
                    $problems[] = "Field key {$field['key']} is used twice.";
                }
                $keys[$field['key'] ?? ''] = true;
            }
        }
    }

    /** @param array<string, string> $files @param list<string> $problems */
    private function checkImages(array $files, array &$problems): void
    {
        if (!isset($files['icon.png'])) {
            $problems[] = 'icon.png is required.';
        }
        foreach ($files as $name => $bytes) {
            if (!preg_match('/^(icon|logo|strip|thumbnail|background|footer)(@([23])x)?\.png$/', $name, $m) || !isset(self::IMAGE_LIMITS[$m[1]])) {
                continue;
            }
            $size = @getimagesizefromstring($bytes);
            if ($size === false || ($size['mime'] ?? '') !== 'image/png') {
                $problems[] = "{$name} is not a PNG.";

                continue;
            }
            $scale = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 1;
            [$maxW, $maxH] = self::IMAGE_LIMITS[$m[1]];
            if ($size[0] > $maxW * $scale || $size[1] > $maxH * $scale) {
                $problems[] = "{$name} is {$size[0]}x{$size[1]}, larger than " . ($maxW * $scale) . 'x' . ($maxH * $scale) . '.';
            }
        }
    }
}
