<?php

namespace App\Console\Commands;

use App\Enums\EnquiryStatus;
use App\Mail\FollowUpReminder;
use App\Models\Agent;
use App\Models\Enquiry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('leads:remind')]
#[Description('Email each agent the leads they planned to follow up on today (or earlier)')]
class RemindFollowUps extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $due = Enquiry::query()
            ->whereNotNull('agent_id')
            ->whereDate('follow_up_on', '<=', today())
            ->whereNotIn('status', [EnquiryStatus::Closed, EnquiryStatus::Lost])
            ->with('listing:id,address')
            ->orderBy('follow_up_on')
            ->get()
            ->groupBy('agent_id');

        $sent = 0;

        foreach ($due as $agentId => $enquiries) {
            $agent = Agent::query()->with('user')->find($agentId);

            if ($agent === null || ! $agent->is_active) {
                continue;
            }

            try {
                Mail::to($agent->contactEmail())->send(new FollowUpReminder($agent, $enquiries));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->components->info("Sent {$sent} follow-up ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }
}
