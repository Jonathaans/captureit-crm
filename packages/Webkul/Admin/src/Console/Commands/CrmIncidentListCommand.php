<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Models\CrmSystemIncident;

class CrmIncidentListCommand extends Command
{
    protected $signature =
        'crm:incidents {--all}';

    protected $description =
        'List recent CRM production incidents.';

    public function handle(): int
    {
        $query =
            CrmSystemIncident::query()
                ->latest('last_seen_at');

        if (! $this->option('all')) {
            $query->whereNull('resolved_at');
        }

        $incidents =
            $query
                ->limit(30)
                ->get();

        if ($incidents->isEmpty()) {
            $this->info('Tidak ada incident.');
            return self::SUCCESS;
        }

        foreach ($incidents as $incident) {
            $this->line(
                sprintf(
                    '#%d [%s] x%d %s | %s',
                    $incident->id,
                    strtoupper($incident->level),
                    $incident->occurrence_count,
                    $incident->last_seen_at?->format(
                        'Y-m-d H:i:s'
                    ),
                    $incident->message
                )
            );
        }

        return self::SUCCESS;
    }
}
