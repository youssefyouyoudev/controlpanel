<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\Discovery\NginxWebsiteDiscoveryService;
use App\Services\Discovery\WebsiteSynchronizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteDiscoveryController extends Controller
{
    public function scan(Request $request, NginxWebsiteDiscoveryService $discovery, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorize('viewAny', Website::class);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may scan the local server.');

        $sites = $discovery->scan();
        $auditLogger->record('websites.scanned', $request->user(), null, [
            'target_type' => 'server',
            'target_identifier' => gethostname() ?: 'local',
            'discovered' => count($sites),
        ]);

        return ApiResponse::success(['discovered' => $sites, 'count' => count($sites)]);
    }

    public function sync(Request $request, WebsiteSynchronizationService $synchronizer): JsonResponse
    {
        $this->authorize('viewAny', Website::class);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may synchronize discovered websites.');

        $result = $synchronizer->synchronize($request->user());

        return ApiResponse::success([
            'created' => $result['created'],
            'updated' => $result['updated'],
            'unchanged' => $result['unchanged'],
            'discovered' => $result['discovered'],
            'websites' => WebsiteResource::collection(collect($result['websites']))->resolve($request),
        ]);
    }
}
