<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Workspace;
use App\Support\AttachmentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function store(Request $request, Workspace $workspace, AttachmentStore $store): JsonResponse
    {
        $request->validate(['file' => ['required', ...AttachmentStore::rules()]]);

        $attachment = $store->store($request->file('file'), $workspace, $request->user());

        return response()->json($attachment->toPayload(), 201);
    }

    public function show(Request $request, Attachment $attachment, AttachmentStore $store): Response
    {
        $this->authorize('view', $attachment);

        $inline = $attachment->isImage() && ! $request->boolean('download');
        $disposition = HeaderUtils::makeDisposition(
            $inline ? 'inline' : 'attachment',
            $attachment->original_name,
            preg_replace('/[^\x20-\x7E]/', '_', $attachment->original_name) ?: 'file',
        );

        return response($store->contents($attachment), 200, [
            // Never let the browser guess: non-images are always served as opaque downloads.
            'Content-Type' => $inline ? $attachment->mime : 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Attachment $attachment, AttachmentStore $store): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $store->delete($attachment);

        return response()->json(['ok' => true]);
    }
}
