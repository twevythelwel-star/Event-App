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
 * - Existing handles are copied out of the old free-text instructions where one is written plainly
 *   (a $Cashtag, a Zelle email, or a 10-digit U.S. number), so hosts who already offered these keep them.
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
            if (empty($row->cash_app_cashtag) && preg_match('/(?<![\w$])\$([A-Za-z][A-Za-z0-9_-]{0,19})\b/', (string) ($row->cash_app_instructions ?? ''), $m)) {
                $update['cash_app_cashtag'] = '$' . $m[1];
            }
            if (empty($row->zelle_recipient)) {
                $zelle = (string) ($row->zelle_instructions ?? '');
                if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $zelle, $m)) {
                    $update['zelle_recipient'] = strtolower($m[0]);
                } elseif (preg_match('/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $zelle, $m)) {
                    $update['zelle_recipient'] = trim($m[0]);
                }
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

    public function down(): void
    {
        Schema::table('host_payment_policies', function (Blueprint $table) {
            $table->dropColumn(['cash_app_cashtag', 'zelle_recipient']);
        });
    }
};
