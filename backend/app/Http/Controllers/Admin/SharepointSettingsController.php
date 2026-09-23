<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SharepointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SharepointSettingsController extends Controller
{
    public function __construct(
        private readonly SharepointService $sharepoint,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->sharepoint->getAdminConfig());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_url' => 'nullable|url|max:2000',
        ]);

        $this->sharepoint->updateFolderUrl($validated['folder_url'] ?? null);

        return response()->json([
            'success' => true,
            'config' => $this->sharepoint->getAdminConfig(),
        ]);
    }

    public function test(): JsonResponse
    {
        try {
            return response()->json($this->sharepoint->testConnection());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('SharePoint test connection failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Verbindungstest fehlgeschlagen.'], 500);
        }
    }
}
