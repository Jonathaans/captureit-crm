<?php

namespace Webkul\Admin\Http\Controllers\System;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\CrmAuditLog;
use Webkul\Admin\Models\CrmSystemIncident;

class SystemControlController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view(
            'admin::system-control.index',
            [
                'auditCount' =>
                    CrmAuditLog::query()->count(),

                'openIncidentCount' =>
                    CrmSystemIncident::query()
                        ->whereNull('resolved_at')
                        ->count(),

                'latestIncident' =>
                    CrmSystemIncident::query()
                        ->latest('last_seen_at')
                        ->first(),
            ]
        );
    }

    public function auditLogs(
        Request $request
    ): View {
        $this->authorizeAccess();

        $query =
            CrmAuditLog::query()
                ->latest('id');

        if ($request->filled('table')) {
            $query->where(
                'table_name',
                $request->input('table')
            );
        }

        if ($request->filled('action')) {
            $query->where(
                'action',
                $request->input('action')
            );
        }

        if ($request->filled('q')) {
            $search =
                '%'
                .trim(
                    (string) $request->input('q')
                )
                .'%';

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'user_name',
                            'like',
                            $search
                        )
                        ->orWhere(
                            'record_id',
                            'like',
                            $search
                        )
                        ->orWhere(
                            'route_name',
                            'like',
                            $search
                        );
                }
            );
        }

        return view(
            'admin::system-control.audit-logs',
            [
                'logs' =>
                    $query
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }

    public function incidents(): View
    {
        $this->authorizeAccess();

        return view(
            'admin::system-control.incidents',
            [
                'incidents' =>
                    CrmSystemIncident::query()
                        ->orderByRaw(
                            'resolved_at IS NULL DESC'
                        )
                        ->latest('last_seen_at')
                        ->paginate(50),
            ]
        );
    }

    public function resolveIncident(
        int $id
    ): RedirectResponse {
        $this->authorizeAccess();

        CrmSystemIncident::query()
            ->findOrFail($id)
            ->update([
                'resolved_at' => now(),
            ]);

        session()->flash(
            'success',
            'Incident ditandai resolved.'
        );

        return back();
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'system-control'
            )
        ) {
            abort(403);
        }
    }
}
