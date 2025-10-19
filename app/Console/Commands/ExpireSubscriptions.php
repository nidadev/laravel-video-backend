<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan subscriptions:expire
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     */
    protected $description = 'Expire subscriptions whose end_date has passed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Get all active subscriptions whose end_date < now
        $expiredCount = Subscription::where('status', 'active')
            ->where('end_date', '<', $now)
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            $this->info("✅ $expiredCount subscriptions expired successfully.");
        } else {
            $this->info("No expired subscriptions found.");
        }

        return 0;
    }
}
