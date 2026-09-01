<?php

namespace Webkul\Admin\Http\Controllers\UserEmail;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailMessage;
use Webkul\Admin\Services\UserEmailAttachmentService;
use Webkul\Admin\Services\UserEmailDeliveryService;
use Webkul\Admin\Services\UserEmailSendService;

class MyEmailFolderController extends Controller
{
    public function drafts(): View
    {
        return $this->folderView(
            'Draft',
            'DRAFT'
        );
    }

    public function outbox(): View
    {
        return $this->folderView(
            'Outbox',
            'OUTBOX'
        );
    }

    public function trash(): View
    {
        return $this->folderView(
            'Trash',
            'TRASH'
        );
    }

    public function editDraft(
        int $id,
        UserEmailSendService $sender
    ): View {
        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $draft =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'folder',
                    'DRAFT'
                )
                ->findOrFail(
                    $id
                );

        return view(
            'admin::user-email.compose',
            [
                'account' => $account,
                'replyTo' => null,
                'draftMessage' => $draft,
                'to' =>
                    $sender
                        ->addressesToInput(
                            $draft->to_emails
                        ),
                'cc' =>
                    $sender
                        ->addressesToInput(
                            $draft->cc_emails
                        ),
                'subject' =>
                    (string) $draft->subject,
                'body' =>
                    (string) $draft->text_body,
            ]
        );
    }

    public function saveDraft(
        Request $request,
        UserEmailDeliveryService $delivery
    ): RedirectResponse {
        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $validated =
            $request->validate([
                'to' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
                'cc' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
                'subject' => [
                    'nullable',
                    'string',
                    'max:998',
                ],
                'message' => [
                    'nullable',
                    'string',
                    'max:200000',
                ],
                'draft_message_id' => [
                    'nullable',
                    'integer',
                ],
                'reply_to_message_id' => [
                    'nullable',
                    'integer',
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

        try {
            $draft =
                $this->ownedMessageOrNull(
                    $user->id,
                    $validated[
                        'draft_message_id'
                    ]
                    ?? null,
                    'DRAFT'
                );

            $replyTo =
                $this->ownedMessageOrNull(
                    $user->id,
                    $validated[
                        'reply_to_message_id'
                    ]
                    ?? null
                );

            $draft =
                $delivery->saveDraft(
                    $account,
                    (string) (
                        $validated['to']
                        ?? ''
                    ),
                    (string) (
                        $validated['cc']
                        ?? ''
                    ),
                    (string) (
                        $validated['subject']
                        ?? ''
                    ),
                    (string) (
                        $validated['message']
                        ?? ''
                    ),
                    $request->file(
                        'attachments',
                        []
                    ),
                    $draft,
                    $replyTo
                );

            session()->flash(
                'success',
                'Draft berhasil disimpan.'
            );

            return redirect()->route(
                'admin.my-email.drafts.edit',
                $draft->id
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                $exception->getMessage()
            );

            return back()
                ->withInput();
        }
    }

    public function retry(
        int $id,
        UserEmailDeliveryService $delivery
    ): RedirectResponse {
        $user =
            $this->user();

        $account =
            $this->account(
                $user->id
            );

        $message =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'folder',
                    'OUTBOX'
                )
                ->findOrFail(
                    $id
                );

        try {
            $delivery->retry(
                $account,
                $message
            );

            session()->flash(
                'success',
                'Email berhasil dikirim dari Outbox.'
            );

            return redirect()->route(
                'admin.my-email.sent.show',
                $message->id
            );
        } catch (Throwable $exception) {
            session()->flash(
                'warning',
                $exception->getMessage()
            );

            return back();
        }
    }

    public function moveToTrash(
        int $id
    ): RedirectResponse {
        $user =
            $this->user();

        $message =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->findOrFail(
                    $id
                );

        if ($message->folder !== 'TRASH') {
            $message->original_folder =
                $message->folder;

            $message->folder =
                'TRASH';

            $message->save();
        }

        session()->flash(
            'success',
            'Email dipindahkan ke Trash.'
        );

        return redirect()->route(
            'admin.my-email.trash'
        );
    }

    public function restore(
        int $id
    ): RedirectResponse {
        $user =
            $this->user();

        $message =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'folder',
                    'TRASH'
                )
                ->findOrFail(
                    $id
                );

        $target =
            trim(
                (string) (
                    $message->original_folder
                    ?? ''
                )
            );

        if (
            ! in_array(
                $target,
                [
                    'INBOX',
                    'DRAFT',
                    'OUTBOX',
                    'CRM_SENT',
                ],
                true
            )
        ) {
            $target =
                $message->direction
                    === 'incoming'
                        ? 'INBOX'
                        : 'CRM_SENT';
        }

        $message->folder =
            $target;

        $message->original_folder =
            null;

        $message->save();

        session()->flash(
            'success',
            'Email berhasil direstore.'
        );

        return back();
    }

    public function destroy(
        int $id,
        UserEmailAttachmentService $attachments
    ): RedirectResponse {
        $user =
            $this->user();

        $message =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'folder',
                    'TRASH'
                )
                ->findOrFail(
                    $id
                );

        $attachments
            ->deleteMessageFiles(
                $message
            );

        $message->delete();

        session()->flash(
            'success',
            'Email dihapus permanen dari CRM.'
        );

        return back();
    }

    private function folderView(
        string $title,
        string $folder
    ): View {
        $user =
            $this->user();

        $messages =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'folder',
                    $folder
                )
                ->latest(
                    $folder === 'DRAFT'
                        ? 'updated_at'
                        : 'id'
                )
                ->paginate(
                    40
                );

        return view(
            'admin::user-email.folder',
            [
                'folderTitle' =>
                    $title,
                'folder' =>
                    $folder,
                'messages' =>
                    $messages,
            ]
        );
    }

    private function user()
    {
        $user =
            auth()
                ->guard('user')
                ->user();

        abort_unless(
            $user,
            403
        );

        return $user;
    }

    private function account(
        int $userId
    ): UserEmailAccount {
        return UserEmailAccount::query()
            ->where(
                'user_id',
                $userId
            )
            ->firstOrFail();
    }

    private function ownedMessageOrNull(
        int $userId,
        mixed $id,
        ?string $folder = null
    ): ?UserEmailMessage {
        if (! $id) {
            return null;
        }

        $query =
            UserEmailMessage::query()
                ->where(
                    'user_id',
                    $userId
                );

        if ($folder !== null) {
            $query->where(
                'folder',
                $folder
            );
        }

        return $query
            ->findOrFail(
                (int) $id
            );
    }
}
