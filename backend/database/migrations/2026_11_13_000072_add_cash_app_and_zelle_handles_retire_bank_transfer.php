<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods become Cash App, Zelle and cash in person; bank transfer is retired.
 *
 * - host_payment_policies.cash_app_cashtag / zelle_recipient: where customers send the payment. A method is
 *   offered only once its handle is set. Free-text instructions stay as optional extra notes.
 * - Existing handles are copied out of the old free-text instructions only when exactly one is written there
 *   (one $Cashtag, one Zelle email, or one 10-digit U.S. number and no other long number), so hosts who already
 *   offered these keep them. Anything ambiguous is left blank and the method stays off until the host adds it.
 * - enable_bank is switched off everywhere. bank_transfer_instructions, require_transfer_reference and
 *   require_sender_name are kept (not dropped) so older orders and settings stay readable.
 * - The platform's allowed methods swap "bank" for "zelle" and "cashapp".
 *
 * Orders already placed with method "bank" are untouched and still display as "Bank transfer".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_payment_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('host_payment_policies', 'cash_app_cashtag')) {
                $table->string('cash_app_cashtag', 21)->nullable();
            }
            if (!Schema::hasColumn('host_payment_policies', 'zelle_recipient')) {
                $table->string('zelle_recipient', 190)->nullable();
            }
        });

        DB::table('host_payment_policies')->orderBy('id')->each(function (object $row) {
            $update = ['enable_bank' => false];
            if (empty($row->cash_app_cashtag) && ($tag = self::onlyCashtag((string) ($row->cash_app_instructions ?? ''))) !== null) {
                $update['cash_app_cashtag'] = $tag;
            }
            if (empty($row->zelle_recipient) && ($to = self::onlyZelleRecipient((string) ($row->zelle_instructions ?? ''))) !== null) {
                $update['zelle_recipient'] = $to;
            }
            DB::table('host_payment_policies')->where('id', $row->id)->update($update);
        });

        DB::table('system_platform_settings')->orderBy('id')->each(function (object $row) {
            $methods = json_decode((string) ($row->supported_payment_methods ?? 'null'), true);
            if (!is_array($methods) || !in_array('bank', $methods, true)) {
                return;
            }
            $methods = array_values(array_unique([...array_diff($methods, ['bank']), 'zelle', 'cashapp']));
            DB::table('system_platform_settings')->where('id', $row->id)->update(['supported_payment_methods' => json_encode($methods)]);
        });
    }

    /**
     * The $Cashtag written in old instructions, only when there is exactly one candidate. Currency codes ($USD) are
     * not cashtags. Anything ambiguous is left for the host to enter: a wrong handle would send money to a stranger.
     */
    private static function onlyCashtag(string $text): ?string
    {
        preg_match_all('/(?<![\w$])\$([A-Za-z][A-Za-z0-9_-]{0,19})(?![\w-])/', $text, $m);
        $tags = array_values(array_unique(array_filter($m[1], fn (string $t) => preg_match('/^[A-Z]{3}$/', $t) !== 1)));

        return count($tags) === 1 ? '$' . $tags[0] : null;
    }

    /**
     * The Zelle email or phone written in old instructions, only when it is the single one there: exactly one
     * email, or no email and exactly one 10-digit U.S. number. Anything else (a help line next to the Zelle
     * number, an account number) is left for the host to enter.
     */
    private static function onlyZelleRecipient(string $text): ?string
    {
        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $emails);
        $emails = array_values(array_unique(array_map('strtolower', $emails[0])));
        if (count($emails) === 1) {
            return $emails[0];
        }
        if ($emails !== []) {
            return null;
        }
        preg_match_all('/(?<!\d)(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}(?!\d)/', $text, $phones);
        $digits = array_values(array_unique(array_map(fn (string $p) => substr(preg_replace('/\D/', '', $p), -10), $phones[0])));
        // Any other long run of digits (an account or reference number) makes the text ambiguous.
        $otherDigits = preg_replace('/(?<!\d)(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}(?!\d)/', '', $text);

        return count($digits) === 1 && preg_match('/\d{5,}/', (string) $otherDigits) !== 1 ? trim($phones[0][0]) : null;
    }

    public function down(): void
    {
        Schema::table('host_payment_policies', function (Blueprint $table) {
            $table->dropColumn(['cash_app_cashtag', 'zelle_recipient']);
        });
    }
};
