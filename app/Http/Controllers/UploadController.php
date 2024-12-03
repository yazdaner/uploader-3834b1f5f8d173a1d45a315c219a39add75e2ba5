<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $sliceFile = $request->file('sliceFile');
        $path = 'public_html/chunks/' . $sliceFile->getClientOriginalName();
        $name = basename($path, '.part');
        $types = ["mp4", "webm", "avc"];
        $ex = explode('.', $name);

        if (sizeof($ex) > 0 && in_array(strtolower($ex[sizeof($ex) - 1]), $types)) {
            $part = $request->get('part');
            $append = true;

            // Check if the file exists and is correctly aligned for the next chunk
            if ($part > 0 && Storage::disk('ftp')->exists($path)) {
                $existingFile = Storage::disk('ftp')->get($path);
                if (strlen($existingFile) != $part * 500000) {
                    $append = false;
                    return response()->json(['status' => 'error', 'message' => 'File size mismatch']);
                }
            }

            if ($append) {
                // Read the current content, append the new chunk, and save back
                $currentContent = Storage::disk('ftp')->exists($path)
                    ? Storage::disk('ftp')->get($path)
                    : '';

                $newContent = $currentContent . $sliceFile->get();
                Storage::disk('ftp')->put($path, $newContent);

                \Log::info('Uploaded part: ' . $part);
            }

            // If this is the final chunk, move the file and save to DB
            if ($request->get('latest') == 'true') {
                $finalName = uniqid() . '_' . $name;
                Storage::disk('ftp')->move($path, "public_html/video/{$finalName}");
                return response()->json(['status' => 'ok', 'name' => $finalName]);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid file type or request']);
    }
}
