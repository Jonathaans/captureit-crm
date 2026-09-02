<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\InternalConversation;
use Webkul\Admin\Models\InternalConversationMember;
use Webkul\Admin\Models\InternalMessage;
use Webkul\Admin\Services\InternalChatService;
use Webkul\Admin\Services\WorkflowNotificationService;

class InternalChatController extends Controller
{
    public function index(
        Request $request,
        InternalChatService $chat
    ): View {
        $user = $this->user();

        $conversationId = $request->integer('conversation');

        $conversation = null;
        $messages = collect();

        if ($conversationId > 0) {
            $chat->assertMember($conversationId, $user->id);

            $conversation = InternalConversation::query()
                ->findOrFail($conversationId);

            $messages = InternalMessage::query()
                ->with('attachments')
                ->where('conversation_id', $conversationId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->limit(300)
                ->get();

            /* Opening the conversation means all currently visible messages are read. */
            $chat->markRead($conversationId, $user->id);
        }

        $users = DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', '<>', $user->id)
            ->when(
                Schema::hasColumn('users', 'status'),
                fn ($query) => $query->where('users.status', 1)
            )
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'roles.name as role_name',
            ])
            ->get();

        $conversationRows = DB::table('internal_conversation_members as m')
            ->join(
                'internal_conversations as c',
                'c.id',
                '=',
                'm.conversation_id'
            )
            ->where('m.user_id', $user->id)
            ->where('c.type', 'direct')
            ->orderByDesc('c.updated_at')
            ->select([
                'c.id',
                'c.updated_at',
                'm.last_read_at',
            ])
            ->get();

        $conversationList = $conversationRows
            ->map(
                function ($row) use ($user) {
                    $other = DB::table('internal_conversation_members as m')
                        ->join('users', 'users.id', '=', 'm.user_id')
                        ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                        ->where('m.conversation_id', $row->id)
                        ->where('m.user_id', '<>', $user->id)
                        ->select([
                            'users.id',
                            'users.name',
                            'users.email',
                            'roles.name as role_name',
                        ])
                        ->first();

                    $lastMessage = DB::table('internal_messages')
                        ->where('conversation_id', $row->id)
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first();

                    $unreadQuery = DB::table('internal_messages')
                        ->where('conversation_id', $row->id)
                        ->where('user_id', '<>', $user->id)
                        ->whereNull('deleted_at');

                    if ($row->last_read_at) {
                        $unreadQuery->where('created_at', '>', $row->last_read_at);
                    }

                    return (object) [
                        'id' => $row->id,
                        'other' => $other,
                        'last_message' => $lastMessage,
                        'unread_count' => (int) $unreadQuery->count(),
                    ];
                }
            );

        $senderIds = $messages
            ->pluck('user_id')
            ->unique()
            ->values();

        $senderNames = $senderIds->isEmpty()
            ? collect()
            : DB::table('users')
                ->whereIn('id', $senderIds)
                ->pluck('name', 'id');

        $activeOtherUser = null;
        $activeReadUpToId = 0;

        if ($conversation) {
            $activeOtherUser = DB::table('internal_conversation_members as m')
                ->join('users', 'users.id', '=', 'm.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('m.conversation_id', $conversation->id)
                ->where('m.user_id', '<>', $user->id)
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                    'm.last_read_at',
                ])
                ->first();

            $activeReadUpToId = $this->readUpToMessageId(
                (int) $conversation->id,
                (int) $user->id,
                $activeOtherUser?->last_read_at
            );
        }

        return view(
            'admin::internal-communication.chat',
            compact(
                'conversation',
                'conversationList',
                'messages',
                'senderNames',
                'activeOtherUser',
                'activeReadUpToId',
                'users'
            )
        );
    }

    public function startDirect(
        int $userId,
        InternalChatService $chat
    ): RedirectResponse {
        $user = $this->user();

        $conversation = $chat->directConversation(
            $user->id,
            $userId
        );

        return redirect()->route(
            'admin.internal-chat.index',
            [
                'conversation' => $conversation->id,
            ]
        );
    }

    public function send(
        Request $request,
        int $conversationId,
        InternalChatService $chat,
        WorkflowNotificationService $notifications
    ): JsonResponse|RedirectResponse {
        $user = $this->user();

        $validated = $request->validate([
            'body' => [
                'nullable',
                'string',
                'max:20000',
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],
            'attachments.*' => [
                'file',
                'max:10240',
            ],
        ]);

        $message = $chat->sendMessage(
            $conversationId,
            $user->id,
            $validated['body'] ?? null,
            $request->file('attachments', [])
        );

        $recipientIds = InternalConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '<>', $user->id)
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            $notifications->notifyUser(
                (int) $recipientId,
                'internal_chat',
                'Pesan Internal Baru',
                $user->name.' mengirim pesan internal.',
                route(
                    'admin.internal-chat.index',
                    [
                        'conversation' => $conversationId,
                    ]
                ),
                'internal-chat-message:'.$message->id,
                'internal_message',
                $message->id,
                [
                    'sender_user_id' => $user->id,
                    'conversation_id' => $conversationId,
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json(
                $this->messagePayload($message, $user->name)
            );
        }

        return redirect()->route(
            'admin.internal-chat.index',
            [
                'conversation' => $conversationId,
            ]
        );
    }

    public function messages(
        Request $request,
        int $conversationId,
        InternalChatService $chat
    ): JsonResponse {
        $user = $this->user();

        $chat->assertMember($conversationId, $user->id);

        $after = max(0, $request->integer('after'));

        $messages = InternalMessage::query()
            ->with('attachments')
            ->where('conversation_id', $conversationId)
            ->where('id', '>', $after)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $senderNames = DB::table('users')
            ->whereIn(
                'id',
                $messages
                    ->pluck('user_id')
                    ->unique()
                    ->all()
            )
            ->pluck('name', 'id');

        /* Polling an open conversation also counts as reading it. */
        $chat->markRead($conversationId, $user->id);

        $otherLastReadAt = InternalConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '<>', $user->id)
            ->value('last_read_at');

        $readUpToId = $this->readUpToMessageId(
            $conversationId,
            (int) $user->id,
            $otherLastReadAt
        );

        return response()->json([
            'messages' => $messages
                ->map(
                    fn ($message) => $this->messagePayload(
                        $message,
                        (string) (
                            $senderNames[$message->user_id]
                            ?? 'User'
                        )
                    )
                )
                ->values(),
            'read_up_to_id' => $readUpToId,
        ]);
    }

    private function readUpToMessageId(
        int $conversationId,
        int $senderUserId,
        mixed $otherLastReadAt
    ): int {
        if (! $otherLastReadAt) {
            return 0;
        }

        return (int) (
            InternalMessage::query()
                ->where('conversation_id', $conversationId)
                ->where('user_id', $senderUserId)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $otherLastReadAt)
                ->max('id')
            ?? 0
        );
    }

    private function messagePayload(
        InternalMessage $message,
        string $senderName
    ): array {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'sender_name' => $senderName,
            'body' => $message->body,
            'created_at' => $message
                ->created_at
                ?->format('Y-m-d H:i:s'),
            'attachments' => $message->attachments
                ->map(
                    fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'size' => $attachment->size,
                        'download_url' => route(
                            'admin.internal-chat.attachments.download',
                            $attachment->id
                        ),
                    ]
                )
                ->values(),
        ];
    }

    private function user()
    {
        $user = auth()
            ->guard('user')
            ->user();

        abort_unless($user, 403);

        return $user;
    }
}
