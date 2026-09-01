<?php

namespace Webkul\Admin\Http\Controllers\Financial;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\FinancialPeriodLock;

class FinancialPeriodController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view(
            'admin::financial-periods.index',
            [
                'locks' =>
                    FinancialPeriodLock::query()
                        ->latest('starts_at')
                        ->paginate(24),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->authorizeAccess();

        $validated =
            $request->validate([
                'period' => [
                    'required',
                    'date_format:Y-m',
                ],
                'notes' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);

        $start =
            Carbon::createFromFormat(
                'Y-m',
                $validated['period']
            )->startOfMonth();

        $user =
            auth()->guard('user')->user();

        FinancialPeriodLock::query()
            ->updateOrCreate(
                [
                    'period' =>
                        $validated['period'],
                ],
                [
                    'starts_at' =>
                        $start->toDateString(),

                    'ends_at' =>
                        $start
                            ->copy()
                            ->endOfMonth()
                            ->toDateString(),

                    'locked_by' =>
                        $user?->id,

                    'locked_by_name' =>
                        $user?->name,

                    'locked_at' =>
                        now(),

                    'notes' =>
                        $validated['notes']
                        ?? null,
                ]
            );

        session()->flash(
            'success',
            'Financial period '
            .$validated['period']
            .' CLOSED.'
        );

        return back();
    }

    public function destroy(
        int $id
    ): RedirectResponse {
        $this->authorizeAccess();

        $lock =
            FinancialPeriodLock::query()
                ->findOrFail($id);

        $period =
            $lock->period;

        $lock->delete();

        session()->flash(
            'success',
            'Financial period '
            .$period
            .' reopened.'
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
                'financial-periods'
            )
        ) {
            abort(403);
        }
    }
}
