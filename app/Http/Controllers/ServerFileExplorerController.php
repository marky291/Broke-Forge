<?php

namespace App\Http\Controllers;

use App\Exceptions\ServerFileExplorerException;
use App\Http\Requests\Servers\ServerFileDownloadRequest;
use App\Http\Requests\Servers\ServerFileIndexRequest;
use App\Http\Requests\Servers\ServerFileUploadRequest;
use App\Models\Server;
use App\Support\ServerFileExplorer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServerFileExplorerController extends Controller
{
    public function index(ServerFileIndexRequest $request, Server $server): JsonResponse
    {
        $explorer = new ServerFileExplorer($server);

        try {
            $listing = $explorer->list($request->input('path', ''));
        } catch (ServerFileExplorerException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }

        return response()->json($listing);
    }

    public function store(ServerFileUploadRequest $request, Server $server): JsonResponse
    {
        $explorer = new ServerFileExplorer($server);

        try {
            $explorer->upload($request->input('path', ''), $request->file('file'));
        } catch (ServerFileExplorerException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'File uploaded successfully.',
        ]);
    }

    public function download(ServerFileDownloadRequest $request, Server $server): BinaryFileResponse|JsonResponse
    {
        $explorer = new ServerFileExplorer($server);

        try {
            $result = $explorer->download($request->input('path'));
        } catch (ServerFileExplorerException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }

        return response()
            ->download($result['path'], $result['filename'])
            ->deleteFileAfterSend(true);
    }
}
