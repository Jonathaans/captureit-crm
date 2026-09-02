<?php

namespace Webkul\Admin\Http\Controllers\InternalCommunication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\InternalMessage;

class InternalChatAuditController extends Controller
{
    public function index(
        Request $request
    ): View {
        $this->authorizeAudit();

        $status =
            strtolower(
                trim(
                    (string) $request->query(
                        'status',
                        ''
                    )
                )
            );

        $query =
            InternalMessage::query()
                ->from(
                    'internal_messages as m'
                )
                ->leftJoin(
                    'users as sender',
                    'sender.id',
                    '=',
                    'm.user_id'
                )
                ->select([
                    'm.*',
                    'sender.name as sender_name',
                    'sender.email as sender_email',
                ]);

        if (
            in_array(
                $status,
                [
                    'active',
                    'edited',
                    'deleted',
                ],
                true
            )
        ) {
            if ($status === 'deleted') {
                $query->whereNotNull(
                    'm.deleted_at'
                );
            } elseif ($status === 'edited') {
                $query
                    ->whereNull(
                        'm.deleted_at'
                    )
                    ->whereNotNull(
                        'm.edited_at'
                    );
            } else {
                $query
                    ->whereNull(
                        'm.deleted_at'
                    )
                    ->whereNull(
                        'm.edited_at'
                    );
            }
        }

        $search =
            trim(
                (string) $request->query(
                    'q',
                    ''
                )
            );

        if ($search !== '') {
            $query->where(
                function ($sub) use ($search) {
                    $sub
                        ->where(
                            'm.body',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'sender.name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'sender.email',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        $from =
            trim(
                (string) $request->query(
                    'from',
                    ''
                )
            );

        if ($from !== '') {
            $query->whereDate(
                'm.created_at',
                '>=',
                $from
            );
        }

        $to =
            trim(
                (string) $request->query(
                    'to',
                    ''
                )
            );

        if ($to !== '') {
            $query->whereDate(
                'm.created_at',
                '<=',
                $to
            );
        }

        $messages =
            $query
                ->orderByDesc(
                    'm.id'
                )
                ->paginate(
                    50
                )
                ->withQueryString();

        $messages
            ->getCollection()
            ->transform(
                function ($message) {
                    $message->recipient_names =
                        $this->recipientNames(
                            (int) $message
                                ->conversation_id,
                            (int) $message
                                ->user_id
                        );

                    $message->audit_count =
                        DB::table(
                            'internal_message_audits'
                        )
                            ->where(
                                'message_id',
                                $message->id
                            )
                            ->count();

                    return $message;
                }
            );

        $summary = [
            'total' =>
                DB::table(
                    'internal_messages'
                )
                    ->count(),

            'edited' =>
                DB::table(
                    'internal_messages'
                )
                    ->whereNotNull(
                        'edited_at'
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->count(),

            'deleted' =>
                DB::table(
                    'internal_messages'
                )
                    ->whereNotNull(
                        'deleted_at'
                    )
                    ->count(),
        ];

        return view(
            'admin::internal-communication.audit.index',
            compact(
                'messages',
                'summary',
                'status',
                'search',
                'from',
                'to'
            )
        );
    }

    public function show(
        int $messageId
    ): View {
        $this->authorizeAudit();

        $message =
            InternalMessage::query()
                ->with(
                    'attachments'
                )
                ->where(
                    'id',
                    $messageId
                )
                ->firstOrFail();

        $sender =
            DB::table(
                'users'
            )
                ->leftJoin(
                    'roles',
                    'roles.id',
                    '=',
                    'users.role_id'
                )
                ->where(
                    'users.id',
                    $message->user_id
                )
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                ])
                ->first();

        $recipients =
            DB::table(
                'internal_conversation_members as cm'
            )
                ->join(
                    'users',
                    'users.id',
                    '=',
                    'cm.user_id'
                )
                ->leftJoin(
                    'roles',
                    'roles.id',
                    '=',
                    'users.role_id'
                )
                ->where(
                    'cm.conversation_id',
                    $message
                        ->conversation_id
                )
                ->where(
                    'cm.user_id',
                    '<>',
                    $message->user_id
                )
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.name as role_name',
                    'cm.last_read_at',
                ])
                ->get();

        $audits =
            DB::table(
                'internal_message_audits as a'
            )
                ->leftJoin(
                    'users as actor',
                    'actor.id',
                    '=',
                    'a.actor_user_id'
                )
                ->where(
                    'a.message_id',
                    $message->id
                )
                ->orderBy(
                    'a.created_at'
                )
                ->orderBy(
                    'a.id'
                )
                ->select([
                    'a.*',
                    'actor.name as actor_name',
                    'actor.email as actor_email',
                ])
                ->get();

        $readAt =
            $recipients
                ->filter(
                    fn ($recipient) =>
                        ! empty(
                            $recipient
                                ->last_read_at
                        )
                        && strtotime(
                            (string) $recipient
                                ->last_read_at
                        ) >= strtotime(
                            (string) $message
                                ->created_at
                        )
                )
                ->min(
                    'last_read_at'
                );

        return view(
            'admin::internal-communication.audit.show',
            compact(
                'message',
                'sender',
                'recipients',
                'audits',
                'readAt'
            )
        );
    }

    private function recipientNames(
        int $conversationId,
        int $senderUserId
    ): string {
        return DB::table(
            'internal_conversation_members as cm'
        )
            ->join(
                'users',
                'users.id',
                '=',
                'cm.user_id'
            )
            ->where(
                'cm.conversation_id',
                $conversationId
            )
            ->where(
                'cm.user_id',
                '<>',
                $senderUserId
            )
            ->orderBy(
                'users.name'
            )
            ->pluck(
                'users.name'
            )
            ->implode(
                ', '
            );
    }

    private function authorizeAudit(): void
    {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        $roleName =
            DB::table(
                'roles'
            )
                ->where(
                    'id',
                    $user->role_id
                )
                ->value(
                    'name'
                );

        $allowed =
            collect(
                config(
                    'internal_chat_audit.role_names',
                    []
                )
            )
                ->map(
                    fn ($value) =>
                        strtolower(
                            trim(
                                (string) $value
                            )
                        )
                )
                ->filter()
                ->values();

        abort_unless(
            $roleName
            && $allowed->contains(
                strtolower(
                    trim(
                        (string) $roleName
                    )
                )
            ),
            403
        );
    }
}
