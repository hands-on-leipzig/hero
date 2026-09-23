<?php

namespace App\Http\Controllers;

use App\Services\SharepointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SharepointController extends Controller
{
    public function __construct(
        private readonly SharepointService $sharepoint,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->sharepoint->getStatus());
    }

    public function documents(Request $request): JsonResponse
    {
        if (! $this->sharepoint->isConfigured()) {
            return response()->json(['configured' => false, 'items' => [], 'breadcrumbs' => []]);
        }

        $validated = $request->validate(['item_id' => 'nullable|string|max:256']);

        try {
            $data = $this->sharepoint->listFolder($validated['item_id'] ?? null);

            return response()->json(['configured' => true, ...$data]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'configured' => true,
                'error' => $e->getMessage(),
                'items' => [],
                'breadcrumbs' => [],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('SharePoint list documents failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Dokumente konnten nicht geladen werden.'], 500);
        }
    }

    public function stream(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'drive_id' => 'required|string|max:256',
            'item_id' => 'required|string|max:256',
        ]);

        try {
            $file = $this->sharepoint->streamFileContent($validated['drive_id'], $validated['item_id']);

            return response($file['body'], 200, [
                'Content-Type' => $file['content_type'],
                'Content-Disposition' => 'inline; filename="'.addslashes($file['filename']).'"',
                'Content-Length' => (string) strlen($file['body']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('SharePoint file stream failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Datei konnte nicht geladen werden.'], 500);
        }
    }
}
