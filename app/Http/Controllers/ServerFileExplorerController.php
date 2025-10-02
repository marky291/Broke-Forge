<?php

namespace App\Http\Controllers;

use App\Exceptions\ServerFileExplorerException;
use App\Http\Requests\Servers\ServerFileDownloadRequest;
use App\Http\Requests\Servers\ServerFileIndexRequest;
use App\Http\Requests\Servers\ServerFileUploadRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Explorer\SiteFileExplorer;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServerFileExplorerController extends Controller
{
    public function show(Server $server, ServerSite $site): Response
    {
        return Inertia::render('servers/explorer', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'ssh_port',
                'private_ip',
                'connection',
                'created_at',
                'updated_at',
            ]),
            'site' => $site->only([
                'id',
                'domain',
                'document_root',
                'status',
                'git_status',
            ]),
        ]);
    }

    public function index(ServerFileIndexRequest $request, Server $server, ServerSite $site): JsonResponse
    {
        $explorer = new SiteFileExplorer($site);

        try {
            $listing = $explorer->list($request->input('path', ''));
        } catch (ServerFileExplorerException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }

        return response()->json($listing);
    }

    public function store(ServerFileUploadRequest $request, Server $server, ServerSite $site): JsonResponse
    {
        $explorer = new SiteFileExplorer($site);

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

    public function download(ServerFileDownloadRequest $request, Server $server, ServerSite $site): BinaryFileResponse|JsonResponse
    {
        $explorer = new SiteFileExplorer($site);

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
