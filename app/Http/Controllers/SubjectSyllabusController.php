<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubjectSyllabusController extends Controller
{
    public function stream(Request $request, Subject $subject)
    {
        if (!filled($subject->syllabus_path)) {
            abort(404);
        }

        if (!$this->isAuthorizedStreamRequest($request, $subject)) {
            abort(403);
        }

        $path = (string) $subject->syllabus_path;
        $disk = Storage::disk('local')->exists($path)
            ? 'local'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        if (!$disk) {
            abort(404);
        }

        $fullPath = Storage::disk($disk)->path($path);
        $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Subject $subject)
    {
        if (!filled($subject->syllabus_path)) {
            abort(404);
        }

        $streamToken = bin2hex(random_bytes(24));
        $tokenSessionKey = 'syllabus_stream_token_' . $subject->id;
        $tokenUsedSessionKey = 'syllabus_stream_token_used_' . $subject->id;

        session([
            $tokenSessionKey => $streamToken,
            $tokenUsedSessionKey => false,
        ]);

        $streamUrl = route('client.subject-syllabus.stream', [
            'subject' => $subject->id,
            'token' => $streamToken,
        ]);

        $extension = strtolower((string) pathinfo((string) $subject->syllabus_path, PATHINFO_EXTENSION));
        $previewType = $extension === 'pdf' ? 'pdf' : 'download';

        return view('client.syllabus-preview', [
            'subject' => $subject,
            'downloadUrl' => $streamUrl,
            'downloadFilename' => $subject->syllabus_original_name ?: basename((string) $subject->syllabus_path),
            'officeEmbedUrl' => 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($streamUrl),
            'previewType' => $previewType,
        ]);
    }

    private function isAuthorizedStreamRequest(Request $request, Subject $subject): bool
    {
        if ($request->hasValidSignature()) {
            return true;
        }

        $token = (string) $request->query('token', '');
        $tokenSessionKey = 'syllabus_stream_token_' . $subject->id;
        $tokenUsedSessionKey = 'syllabus_stream_token_used_' . $subject->id;
        $sessionToken = (string) session($tokenSessionKey, '');
        $tokenWasUsed = (bool) session($tokenUsedSessionKey, false);

        if ($token === '' || $sessionToken === '' || $tokenWasUsed) {
            return false;
        }

        if (!hash_equals($sessionToken, $token)) {
            return false;
        }

        session([$tokenUsedSessionKey => true]);
        session()->forget($tokenSessionKey);

        return true;
    }
}

