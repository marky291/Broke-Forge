<?php

namespace App\Http\Controllers;

use App\Exceptions\ServerFileExplorerException;
use App\Http\Controllers\Concerns\PreparesSiteData;
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
    use PreparesSiteData;

    public function show(Server $server, ServerSite $site): Response
    {
        return Inertia::render('servers/explorer', [
            'server' => $this->prepareServerData($server, ['ssh_port']),
            'site' => $this->prepareSiteData($site, ['document_root']),
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
