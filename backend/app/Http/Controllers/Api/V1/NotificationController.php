<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(50)->get();

        return ApiResponse::success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection($notifications)->resolve($request),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === $request->user()::class, 404);
        $notification->markAsRead();

        return ApiResponse::success(['notification' => new NotificationResource($notification->refresh())]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success(null, 'Notifications marked read.');
    }
}
