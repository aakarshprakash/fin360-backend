<?php

namespace App\Console\Commands;

use App\Models\Accounting\Quotation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'accounting:expire-quotations';
    protected $description = 'Mark quotations with valid_until < today as expired';

    public function handle()
    {
        $today = Carbon::today();

        $expired = Quotation::whereNotIn('status', ['expired', 'converted', 'rejected'])
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $today)
            ->update(['status' => 'expired']);

        $this->info("Expired {$expired} quotation(s).");

        return 0;
    }
}

