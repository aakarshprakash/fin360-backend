<?php

use App\Models\Accounting\AccountingSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Business & tax details, accounting preferences and the invoice template used
 * to be stored per user, so each staff member printed a different letterhead.
 * They are now organisation-wide: one row with user_id = NULL.
 *
 * The company row is seeded from the most complete existing profile (most
 * filled letterhead fields; latest update wins ties). That user's old row is
 * kept as history; every other per-user copy is deleted.
 */
return new class extends Migration
{
    private array $letterheadKeys = [
        'companyName', 'address', 'phone', 'mobile', 'email', 'website', 'state', 'stateCode',
        'gstNumber', 'pan', 'cin', 'bankDetails', 'companyLogo', 'sealSignatureImage',
    ];

    public function up()
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE accounting_settings MODIFY user_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE invoice_template_settings MODIFY user_id BIGINT UNSIGNED NULL');
        }

        $sourceUserId = $this->seedAccountingSettings();
        $this->seedInvoiceTemplate($sourceUserId);

        // Keep only the source user's old row as history; the other per-user
        // copies were incomplete duplicates and are never read again.
        if ($sourceUserId) {
            foreach (['accounting_settings', 'invoice_template_settings'] as $table) {
                DB::table($table)
                    ->whereNotNull('user_id')
                    ->where('user_id', '!=', $sourceUserId)
                    ->delete();
            }
        }
    }

    public function down()
    {
        DB::table('accounting_settings')->whereNull('user_id')->delete();
        DB::table('invoice_template_settings')->whereNull('user_id')->delete();
    }

    /** @return int|null user_id of the row the company profile was copied from */
    private function seedAccountingSettings(): ?int
    {
        if (DB::table('accounting_settings')->whereNull('user_id')->exists()) {
            return null;
        }

        $best = null;
        $bestScore = -1;
        foreach (DB::table('accounting_settings')->orderBy('updated_at')->get() as $row) {
            $company = AccountingSetting::normalizeKeys(json_decode($row->company ?? '[]', true) ?: []);
            $score = count(array_filter(
                array_intersect_key($company, array_flip($this->letterheadKeys)),
                fn ($v) => $v !== null && $v !== ''
            ));
            if ($score >= $bestScore) { // later rows win ties
                $best = $row;
                $bestScore = $score;
            }
        }

        if (!$best) {
            return null; // fresh install: AccountingSetting::forCompany() creates defaults on first use
        }

        $section = fn ($json) => json_encode(AccountingSetting::normalizeKeys(json_decode($json ?? '[]', true) ?: []));

        DB::table('accounting_settings')->insert([
            'user_id'    => null,
            'company'    => $section($best->company),
            'accounting' => $section($best->accounting),
            'numbering'  => $section($best->numbering),
            'quotation'  => $section($best->quotation),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $best->user_id;
    }

    private function seedInvoiceTemplate(?int $sourceUserId): void
    {
        if (DB::table('invoice_template_settings')->whereNull('user_id')->exists()) {
            return;
        }

        $row = ($sourceUserId ? DB::table('invoice_template_settings')->where('user_id', $sourceUserId)->first() : null)
            ?? DB::table('invoice_template_settings')->orderByDesc('updated_at')->first();

        if (!$row) {
            return; // InvoiceTemplateSetting::forCompany() creates defaults on first use
        }

        DB::table('invoice_template_settings')->insert([
            'user_id'               => null,
            'invoice_prefix'        => $row->invoice_prefix,
            'footer_text'           => $row->footer_text,
            'terms_and_conditions'  => $row->terms_and_conditions,
            'default_payment_terms' => $row->default_payment_terms,
            'invoice_logo'          => $row->invoice_logo,
            'invoice_theme'         => in_array($row->invoice_theme, ['default', 'letterhead'], true) ? $row->invoice_theme : 'default',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }
};
