<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app =
    require_once __DIR__.'/../bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

echo "INTERNAL CHAT UNREAD CURSOR AUDIT\n";
echo "=================================\n\n";

if (
    ! Schema::hasColumn(
        'internal_conversation_members',
        'last_read_message_id'
    )
) {
    echo "FAIL: last_read_message_id belum tersedia.\n";
    exit(1);
}

$members =
    DB::table(
        'internal_conversation_members as member'
    )
        ->join(
            'users',
            'users.id',
            '=',
            'member.user_id'
        )
        ->orderBy(
            'member.conversation_id'
        )
        ->orderBy(
            'member.user_id'
        )
        ->get([
            'member.conversation_id',
            'member.user_id',
            'users.name',
            'member.last_read_message_id',
            'member.last_read_at',
        ]);

foreach ($members as $member) {
    $cursor =
        max(
            0,
            (int) (
                $member->last_read_message_id
                ?? 0
            )
        );

    $maxMessageId =
        (int) (
            DB::table(
                'internal_messages'
            )
                ->where(
                    'conversation_id',
                    $member->conversation_id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->max(
                    'id'
                )
            ?? 0
        );

    $unread =
        (int) DB::table(
            'internal_messages'
        )
            ->where(
                'conversation_id',
                $member->conversation_id
            )
            ->where(
                'user_id',
                '<>',
                $member->user_id
            )
            ->whereNull(
                'deleted_at'
            )
            ->where(
                'id',
                '>',
                $cursor
            )
            ->count();

    echo 'Conversation '
        .$member->conversation_id
        .' | User '
        .$member->user_id
        .' ('
        .$member->name
        .')'
        .' | cursor='
        .$cursor
        .' | max='
        .$maxMessageId
        .' | unread='
        .$unread
        .PHP_EOL;
}

echo "\nDONE\n";
