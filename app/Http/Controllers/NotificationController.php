<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->get();

        return view('notification.index', compact('notifications'));
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->update([
            'Is_Read' => true,
        ]);

        if ($notification->post && $notification->post->topic) {
            return redirect()->route('topics.show', $notification->post->topic);
        }

        return redirect()->route('notifications.index');
    }
}
