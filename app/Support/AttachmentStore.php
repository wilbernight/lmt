<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use finfo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploads outside the web root under random names. Files are only ever
 * served back through AttachmentController after an authorization check.
 */
class AttachmentStore
{
    public const MAX_KB = 20 * 1024;

    /** Extension => accepted MIME types, as detected from the file content. */
    public const ALLOWED = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        'mp3' => ['audio/mpeg'], 'mp4' => ['video/mp4'], 'webm' => ['video/webm', 'audio/webm'], 'ogg' => ['audio/ogg', 'video/ogg'],
    ];

    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', array_keys(self::ALLOWED))];
    }

    public function store(UploadedFile $file, Workspace $workspace, User $user): Attachment
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        // Detected from the file's bytes, never from the client-supplied header or extension.
        $mime = (string) rescue(fn () => (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()), '', report: false); // unreadable (e.g. quarantined by antivirus) means rejected

        if (! in_array($mime, self::ALLOWED[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => 'نوع این فایل مجاز نیست.']);
        }

        $size = (int) $file->getSize();

        if (! $workspace->hasStorageFor($size)) {
            throw ValidationException::withMessages(['file' => 'فضای ذخیره‌سازی میزکار پر شده است.']);
        }

        $path = $file->storeAs(
            'attachments/'.$workspace->id.'/'.now()->format('Y/m'),
            Str::random(40).'.bin',
            'local'
        );

        return DB::transaction(function () use ($file, $workspace, $user, $path, $mime, $size) {
            $attachment = new Attachment(['original_name' => $this->cleanName($file->getClientOriginalName())]);
            $attachment->workspace_id = $workspace->id;
            $attachment->user_id = $user->id;
            $attachment->disk = 'local';
            $attachment->path = $path;
            $attachment->mime = $mime;
            $attachment->size = $size;
            $attachment->sha256 = hash_file('sha256', Storage::disk('local')->path($path));
            $attachment->save();

            $workspace->increment('storage_used', $size);

            return $attachment;
        });
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        Workspace::whereKey($attachment->workspace_id)->where('storage_used', '>=', $attachment->size)->decrement('storage_used', $attachment->size);
        $attachment->delete();
    }

    private function cleanName(string $name): string
    {
        // Strip path separators and control characters; keep Persian letters.
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name) ?? 'file';

        return mb_substr(trim($name, '. '), 0, 200) ?: 'file';
    }
}
