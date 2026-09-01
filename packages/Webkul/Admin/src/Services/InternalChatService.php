<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Admin\Models\InternalConversation;
use Webkul\Admin\Models\InternalConversationMember;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Models\InternalMessageAttachment;

class InternalChatService
{
    public function directConversation(
        int $userId,
        int $otherUserId
    ): InternalConversation {
        if (
            $userId < 1
            || $otherUserId < 1
            || $userId === $otherUserId
        ) {
            throw new RuntimeException(
                'User tujuan chat tidak valid.'
            );
        }

        abort_unless(
            DB::table('users')
                ->where(
                    'id',
                    $otherUserId
                )
                ->exists(),
            404
        );

        $ids = [
            $userId,
            $otherUserId,
        ];

        sort(
            $ids,
            SORT_NUMERIC
        );

        $directKey =
            implode(
                ':',
                $ids
            );

        return DB::transaction(
            function () use (
                $userId,
                $otherUserId,
                $directKey
            ) {
                $conversation =
                    InternalConversation::query()
                        ->where(
                            'direct_key',
                            $directKey
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $conversation) {
                    $conversation =
                        InternalConversation::query()
                            ->create([
                                'type' =>
                                    'direct',

                                'direct_key' =>
                                    $directKey,

                                'created_by' =>
                                    $userId,
                            ]);
                }

                foreach (
                    [
                        $userId,
                        $otherUserId,
                    ]
                    as $memberId
                ) {
                    InternalConversationMember::query()
                        ->firstOrCreate(
                            [
                                'conversation_id' =>
                                    $conversation->id,

                                'user_id' =>
                                    $memberId,
                            ],
                            [
                                'joined_at' =>
                                    now(),

                                'last_read_at' =>
                                    $memberId === $userId
                                        ? now()
                                        : null,
                            ]
                        );
                }

                return $conversation;
            }
        );
    }

    public function assertMember(
        int $conversationId,
        int $userId
    ): InternalConversationMember {
        return InternalConversationMember::query()
            ->where(
                'conversation_id',
                $conversationId
            )
            ->where(
                'user_id',
                $userId
            )
            ->firstOrFail();
    }

    public function markRead(
        int $conversationId,
        int $userId
    ): void {
        $member =
            $this->assertMember(
                $conversationId,
                $userId
            );

        $member->update([
            'last_read_at' =>
                now(),
        ]);
    }

    public function sendMessage(
        int $conversationId,
        int $userId,
        ?string $body,
        array $uploads = []
    ): InternalMessage {
        $this->assertMember(
            $conversationId,
            $userId
        );

        $body =
            trim(
                (string) (
                    $body
                    ?? ''
                )
            );

        if (
            $body === ''
            && empty($uploads)
        ) {
            throw new RuntimeException(
                'Pesan atau attachment harus diisi.'
            );
        }

        return DB::transaction(
            function () use (
                $conversationId,
                $userId,
                $body,
                $uploads
            ) {
                $message =
                    InternalMessage::query()
                        ->create([
                            'conversation_id' =>
                                $conversationId,

                            'user_id' =>
                                $userId,

                            'body' =>
                                $body !== ''
                                    ? $body
                                    : null,
                        ]);

                $this->storeAttachments(
                    $message,
                    $userId,
                    $uploads
                );

                InternalConversation::query()
                    ->whereKey(
                        $conversationId
                    )
                    ->update([
                        'updated_at' =>
                            now(),
                    ]);

                InternalConversationMember::query()
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->update([
                        'last_read_at' =>
                            now(),
                    ]);

                return $message->fresh([
                    'attachments',
                ]);
            }
        );
    }

    public function unreadCount(
        int $userId
    ): int {
        return (int) DB::table(
            'internal_conversation_members as m'
        )
            ->join(
                'internal_messages as msg',
                'msg.conversation_id',
                '=',
                'm.conversation_id'
            )
            ->where(
                'm.user_id',
                $userId
            )
            ->where(
                'msg.user_id',
                '<>',
                $userId
            )
            ->whereNull(
                'msg.deleted_at'
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'm.last_read_at'
                        )
                        ->orWhereColumn(
                            'msg.created_at',
                            '>',
                            'm.last_read_at'
                        );
                }
            )
            ->count();
    }

    private function storeAttachments(
        InternalMessage $message,
        int $userId,
        array $uploads
    ): void {
        $uploads =
            array_values(
                array_filter(
                    $uploads,
                    fn ($upload) =>
                        $upload instanceof UploadedFile
                )
            );

        if (
            count($uploads) > 5
        ) {
            throw new RuntimeException(
                'Maksimal 5 attachment per pesan.'
            );
        }

        foreach ($uploads as $upload) {
            if (
                $upload->getSize() > 10485760
            ) {
                throw new RuntimeException(
                    'Attachment chat maksimal 10 MB per file.'
                );
            }

            $original =
                $this->safeFilename(
                    $upload
                        ->getClientOriginalName()
                );

            $extension =
                pathinfo(
                    $original,
                    PATHINFO_EXTENSION
                );

            $storedName =
                (string) Str::uuid()
                .(
                    $extension !== ''
                        ? '.'
                            .strtolower(
                                preg_replace(
                                    '/[^A-Za-z0-9]/',
                                    '',
                                    $extension
                                )
                                ?? ''
                            )
                        : ''
                );

            $directory =
                'private/internal-chat/'
                .$message->conversation_id
                .'/'
                .$message->id;

            $path =
                $upload->storeAs(
                    $directory,
                    $storedName,
                    'local'
                );

            if (! $path) {
                throw new RuntimeException(
                    'Gagal menyimpan attachment chat.'
                );
            }

            InternalMessageAttachment::query()
                ->create([
                    'message_id' =>
                        $message->id,

                    'user_id' =>
                        $userId,

                    'original_name' =>
                        $original,

                    'mime_type' =>
                        $upload->getMimeType()
                        ?: $upload
                            ->getClientMimeType(),

                    'size' =>
                        $upload->getSize(),

                    'storage_path' =>
                        $path,
                ]);
        }
    }

    private function safeFilename(
        string $name
    ): string {
        $name =
            str_replace(
                [
                    '\\',
                    '/',
                    "\0",
                ],
                '_',
                trim($name)
            );

        $name =
            preg_replace(
                '/[\x00-\x1F\x7F]/u',
                '',
                $name
            )
            ?? $name;

        return $name !== ''
            ? mb_substr(
                $name,
                0,
                240
            )
            : 'attachment.bin';
    }
}
