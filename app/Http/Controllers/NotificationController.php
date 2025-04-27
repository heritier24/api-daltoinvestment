<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    /**
     * Create a new notification (Admin only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $user = Auth::user();
            // if (!$user || $user->role !== 'admin') {
            //     return response()->json([
            //         'message' => 'Unauthorized. Admin access required.',
            //     ], 403);
            // }

            $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image (max 2MB)
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('notifications', 'public');
            }

            // Create the notification
            $notification = Notifications::create([
                'title' => $request->title,
                'message' => $request->message,
                'sender_id' => 1,
                'status' => 'unread',
                'image' => $imagePath, // Store the image path
            ]);

            // Attach the notification to all member users (role: user_client)
            $members = User::where('role', 'user_client')->get();
            $notification->users()->attach($members->pluck('id'), ['is_read' => false]);

            // Transform the image path to a full URL
            if ($notification->image) {
                $notification->image = Storage::url($notification->image);
            }

            return response()->json([
                'message' => 'Notification sent successfully.',
                'data' => $notification,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating notification: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while creating the notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch all notifications for the specified user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json([
                    'message' => 'User ID is required.',
                ], 400);
            }

            $user = User::findOrFail($userId);
            $perPage = $request->query('per_page', 10);
            $notifications = $user->notifications()
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transform the image path to a full URL for each notification
            $notifications->getCollection()->transform(function ($notification) {
                if ($notification->image) {
                    // Generate the storage URL and prepend the APP_URL
                    $imagePath = Storage::url($notification->image);
                    $notification->image = rtrim(env('APP_URL'), '/') . $imagePath;
                }
                return $notification;
            });

            return response()->json([
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'total_pages' => $notifications->lastPage(),
                    'total_items' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                ],
                'message' => 'Notifications fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching notifications.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch a single notification and mark it as read for the specified user.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json([
                    'message' => 'User ID is required.',
                ], 400);
            }

            $user = User::findOrFail($userId);
            $notification = Notifications::with(['sender', 'users' => function ($query) use ($userId) {
                $query->where('users.id', $userId);
            }])->findOrFail($id);

            if (!$user->notifications()->where('notification_id', $id)->exists()) {
                return response()->json([
                    'message' => 'Notification not found or you do not have access to it.',
                ], 404);
            }

            // Mark the notification as read
            $user->notifications()->updateExistingPivot($id, [
                'is_read' => true,
                'read_at' => now(),
            ]);

            // Reload the notification with updated pivot data
            $notification->load(['users' => function ($query) use ($userId) {
                $query->where('users.id', $userId);
            }]);

            // Transform the response to include pivot data directly in the notification object
            $notificationData = $notification->toArray();
            if (!empty($notification->users) && $notification->users->count() > 0) {
                $notificationData['pivot'] = $notification->users[0]->pivot->toArray();
            }

            // Transform the image path to a full URL
            if ($notification->image) {
                $imagePath = Storage::url($notification->image);
                $notificationData['image'] = rtrim(env('APP_URL'), '/') . $imagePath;
            }

            return response()->json([
                'data' => $notificationData,
                'message' => 'Notification fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'notification_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching the notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the count of unread notifications for the specified user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        try {
            $authUser = Auth::user();
            // if (!$authUser) {
            //     return response()->json([
            //         'message' => 'Unauthorized.',
            //     ], 401);
            // }

            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json([
                    'message' => 'User ID is required.',
                ], 400);
            }

            // Security check: Ensure the requested user_id matches the authenticated user
            // if ($authUser->id != $userId) {
            //     return response()->json([
            //         'message' => 'Unauthorized. You can only fetch notifications for your own user ID.',
            //     ], 403);
            // }

            $user = User::findOrFail($userId);
            $unreadCount = $user->notifications()
                ->wherePivot('is_read', false)
                ->count();

            return response()->json([
                'data' => [
                    'unread_count' => $unreadCount,
                ],
                'message' => 'Unread notifications count fetched successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread notifications count: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching unread notifications count.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the specified user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $authUser = Auth::user();
            // if (!$authUser) {
            //     return response()->json([
            //         'message' => 'Unauthorized.',
            //     ], 401);
            // }

            $userId = $request->input('user_id');
            if (!$userId) {
                return response()->json([
                    'message' => 'User ID is required.',
                ], 400);
            }

            // Security check: Ensure the requested user_id matches the authenticated user
            // if ($authUser->id != $userId) {
            //     return response()->json([
            //         'message' => 'Unauthorized. You can only mark notifications for your own user ID.',
            //     ], 403);
            // }

            $user = User::findOrFail($userId);
            $user->notifications()
                ->wherePivot('is_read', false)
                ->update(['notification_user.is_read' => true, 'notification_user.read_at' => now()]);

            return response()->json([
                'message' => 'All notifications marked as read.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while marking notifications as read.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the read status of a notification for the specified user.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleReadStatus(Request $request, $id)
    {
        try {
            $authUser = Auth::user();
            // if (!$authUser) {
            //     return response()->json([
            //         'message' => 'Unauthorized.',
            //     ], 401);
            // }

            $userId = $request->input('user_id');
            if (!$userId) {
                return response()->json([
                    'message' => 'User ID is required.',
                ], 400);
            }

            // Security check: Ensure the requested user_id matches the authenticated user
            // if ($authUser->id != $userId) {
            //     return response()->json([
            //         'message' => 'Unauthorized. You can only update notifications for your own user ID.',
            //     ], 403);
            // }

            $user = User::findOrFail($userId);
            $notification = Notifications::findOrFail($id);
            if (!$user->notifications()->where('notification_id', $id)->exists()) {
                return response()->json([
                    'message' => 'Notification not found or you do not have access to it.',
                ], 404);
            }

            $currentStatus = $user->notifications()->where('notification_id', $id)->first()->pivot->is_read;
            $newStatus = !$currentStatus;

            $user->notifications()->updateExistingPivot($id, [
                'is_read' => $newStatus,
                'read_at' => $newStatus ? now() : null,
            ]);

            return response()->json([
                'message' => 'Notification status updated successfully.',
                'is_read' => $newStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling notification read status: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'notification_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the notification status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
