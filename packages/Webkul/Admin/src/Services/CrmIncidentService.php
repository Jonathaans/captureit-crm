<?php

namespace Webkul\Admin\Services;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CrmIncidentService
{
    private static bool $recording = false;

    public function captureLog(
        MessageLogged $event
    ): void {
        if (
            ! in_array(
                strtolower($event->level),
                [
                    'emergency',
                    'alert',
                    'critical',
                    'error',
                ],
                true
            )
        ) {
            return;
        }

        $exception = $event->context['exception'] ?? null;

        $this->capture(
            level: $event->level,
            message: $event->message,
            context: $event->context,
            exception: $exception instanceof Throwable
                ? $exception
                : null
        );
    }

    public function captureException(
        Throwable $exception,
        string $level = 'error',
        array $context = []
    ): void {
        $this->capture(
            level: $level,
            message: $exception->getMessage(),
            context: $context,
            exception: $exception
        );
    }

    private function capture(
        string $level,
        string $message,
        array $context = [],
        ?Throwable $exception = null
    ): void {
        if (self::$recording) {
            return;
        }

        if (
            ! Schema::hasTable('crm_system_incidents')
        ) {
            return;
        }

        self::$recording = true;

        try {
            unset($context['exception']);

            $routeName = null;
            $url = null;
            $userId = null;

            if (! app()->runningInConsole()) {
                try {
                    $routeName = request()->route()?->getName();
                    $url = request()->fullUrl();
                    $userId = auth()->guard('user')->id();
                } catch (Throwable) {
                    // Keep incident capture alive.
                }
            }

            $file = $exception?->getFile();
            $line = $exception?->getLine();

            $fingerprint = hash(
                'sha256',
                implode('|', [
                    strtolower($level),
                    $message,
                    (string) $file,
                    (string) $line,
                    (string) $routeName,
                ])
            );

            $existing = DB::table('crm_system_incidents')
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($existing) {
                DB::table('crm_system_incidents')
                    ->where('id', $existing->id)
                    ->update([
                        'occurrence_count' =>
                            (int) $existing->occurrence_count + 1,
                        'last_seen_at' => now(),
                        'resolved_at' => null,
                        'updated_at' => now(),
                    ]);

                return;
            }

            DB::table('crm_system_incidents')->insert([
                'fingerprint' => $fingerprint,
                'level' => strtolower($level),
                'message' => $message,
                'context' => $context
                    ? json_encode(
                        $context,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                    : null,
                'file' => $file,
                'line' => $line,
                'route_name' => $routeName,
                'url' => $url,
                'user_id' => $userId,
                'occurrence_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'resolved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // Never recursively log monitoring failure.
        } finally {
            self::$recording = false;
        }
    }
}
