<?php

namespace Webkul\Admin\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Webkul\Admin\Models\GoogleCalendarEvent;
use Webkul\User\Models\User;

class GoogleCalendarService
{
    private ?string $accessToken =
        null;

    public function enabled(): bool
    {
        return (bool) config(
            'google-calendar.enabled'
        );
    }

    public function calendarId(): string
    {
        $calendarId = trim(
            (string) config(
                'google-calendar.calendar_id'
            )
        );

        if ($calendarId === '') {
            throw new RuntimeException(
                'GOOGLE_CALENDAR_ID belum diisi.'
            );
        }

        return $calendarId;
    }

    public function checkConnection(): array
    {
        $calendarId =
            $this->calendarId();

        $response = Http::withToken(
            $this->token()
        )
            ->acceptJson()
            ->get(
                'https://www.googleapis.com/calendar/v3/calendars/'
                .rawurlencode(
                    $calendarId
                )
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Calendar connection gagal: '
                .$response->status()
                .' '
                .$response->body()
            );
        }

        return $response->json();
    }

    public function sync(
        GoogleCalendarEvent $event
    ): GoogleCalendarEvent {
        if (! $event->start_at) {
            throw new RuntimeException(
                'Event belum memiliki Start Date/Time.'
            );
        }

        if (! $event->end_at) {
            throw new RuntimeException(
                'Event belum memiliki End Date/Time.'
            );
        }

        $calendarId =
            $this->calendarId();

        $payload =
            $this->payload(
                $event
            );

        if ($event->google_event_id) {
            $response = Http::withToken(
                $this->token()
            )
                ->acceptJson()
                ->put(
                    'https://www.googleapis.com/calendar/v3/calendars/'
                    .rawurlencode(
                        $calendarId
                    )
                    .'/events/'
                    .rawurlencode(
                        $event->google_event_id
                    ),
                    $payload
                );
        } else {
            $response = Http::withToken(
                $this->token()
            )
                ->acceptJson()
                ->post(
                    'https://www.googleapis.com/calendar/v3/calendars/'
                    .rawurlencode(
                        $calendarId
                    )
                    .'/events',
                    $payload
                );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Calendar sync gagal: '
                .$response->status()
                .' '
                .$response->body()
            );
        }

        $data =
            $response->json();

        $event->fill([
            'google_calendar_id' =>
                $calendarId,

            'google_event_id' =>
                $data['id']
                ?? $event->google_event_id,

            'sync_status' =>
                'synced',

            'sync_error' =>
                null,

            'synced_at' =>
                now(),
        ])->save();

        return $event->refresh();
    }

    private function payload(
        GoogleCalendarEvent $event
    ): array {
        $owner = $event->sales_owner_id
            ? User::query()->find(
                $event->sales_owner_id
            )
            : null;

        $colorId =
            $event->event_status === 'cancelled'
                ? (string) config(
                    'google-calendar.cancelled_color_id',
                    '8'
                )
                : (string) (
                    $owner?->google_calendar_color_id
                    ?: '9'
                );

        $summary =
            $event->event_status === 'cancelled'
                ? '[CANCELLED] '
                    .$event->title
                : $event->title;

        $description = implode(
            "\n",
            array_filter([
                'CRM Lead ID: '
                    .$event->lead_id,

                $owner
                    ? 'Sales Owner: '
                        .$owner->name
                    : null,

                $event->notes
                    ? "\n"
                        .$event->notes
                    : null,
            ])
        );

        $timezone = (string) config(
            'google-calendar.timezone',
            config(
                'app.timezone',
                'Asia/Jakarta'
            )
        );

        return [
            'summary' =>
                $summary,

            'location' =>
                $event->location
                ?: null,

            'description' =>
                $description,

            'colorId' =>
                $colorId,

            'start' => [
                'dateTime' =>
                    $event->start_at
                        ->toIso8601String(),

                'timeZone' =>
                    $timezone,
            ],

            'end' => [
                'dateTime' =>
                    $event->end_at
                        ->toIso8601String(),

                'timeZone' =>
                    $timezone,
            ],
        ];
    }

    private function token(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $credentials =
            $this->credentials();

        $now =
            time();

        $header = [
            'alg' =>
                'RS256',

            'typ' =>
                'JWT',
        ];

        $claims = [
            'iss' =>
                $credentials['client_email'],

            'scope' =>
                'https://www.googleapis.com/auth/calendar',

            'aud' =>
                'https://oauth2.googleapis.com/token',

            'iat' =>
                $now,

            'exp' =>
                $now + 3600,
        ];

        $unsigned =
            $this->base64Url(
                json_encode(
                    $header,
                    JSON_UNESCAPED_SLASHES
                )
            )
            .'.'
            .$this->base64Url(
                json_encode(
                    $claims,
                    JSON_UNESCAPED_SLASHES
                )
            );

        $signature =
            '';

        $signed = openssl_sign(
            $unsigned,
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            throw new RuntimeException(
                'Gagal menandatangani Service Account JWT.'
            );
        }

        $assertion =
            $unsigned
            .'.'
            .$this->base64Url(
                $signature
            );

        $response = Http::asForm()
            ->post(
                'https://oauth2.googleapis.com/token',
                [
                    'grant_type' =>
                        'urn:ietf:params:oauth:grant-type:jwt-bearer',

                    'assertion' =>
                        $assertion,
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google OAuth token gagal: '
                .$response->status()
                .' '
                .$response->body()
            );
        }

        $token =
            $response->json(
                'access_token'
            );

        if (! $token) {
            throw new RuntimeException(
                'Google OAuth tidak mengembalikan access_token.'
            );
        }

        return $this->accessToken =
            (string) $token;
    }

    private function credentials(): array
    {
        $path = (string) config(
            'google-calendar.credentials_path'
        );

        if (
            $path === ''
            || ! is_file(
                $path
            )
        ) {
            throw new RuntimeException(
                'Service Account JSON tidak ditemukan: '
                .$path
            );
        }

        $credentials =
            json_decode(
                file_get_contents(
                    $path
                ),
                true
            );

        if (
            ! is_array(
                $credentials
            )
            || empty(
                $credentials['client_email']
            )
            || empty(
                $credentials['private_key']
            )
        ) {
            throw new RuntimeException(
                'Service Account JSON tidak valid.'
            );
        }

        return $credentials;
    }

    private function base64Url(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode(
                    $value
                ),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
