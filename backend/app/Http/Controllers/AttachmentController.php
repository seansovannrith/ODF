<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public static function storeFiles(Request $request, Document $document): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            $document->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('attachments/'.$document->id, 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    public function store(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($document->visibleTo($user), 403);
        $isMyTurn = $document->comments()->where('user_id', $user->id)->where('status', 'current')->exists();
        abort_unless($user->can('document_edit') || $isMyTurn, 403);

        $request->validate(['attachments' => 'required|array', 'attachments.*' => 'file|max:51200']);
        static::storeFiles($request, $document);
        $document->log('attachment_added');

        return $document->attachments()->get();
    }

    public function download(Request $request, DocumentAttachment $attachment)
    {
        abort_unless($attachment->document && $attachment->document->visibleTo($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'File not found');

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroy(Request $request, DocumentAttachment $attachment)
    {
        abort_unless($attachment->document && $attachment->document->visibleTo($request->user()), 403);
        abort_unless($request->user()->can('document_edit') || $attachment->uploaded_by === $request->user()->id, 403);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
