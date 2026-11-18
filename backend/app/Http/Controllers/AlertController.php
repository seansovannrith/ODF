<?php

namespace App\Http\Controllers;

use App\Models\UserAlert;
use App\Support\TableQuery;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** Bell icon in the navbar. */
    public function mine(Request $request)
    {
        $user = $request->user();

        return [
            'unread' => $user->alerts()->wherePivotNull('read_at')->count(),
            'items' => $user->alerts()->latest('user_alerts.id')->limit(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'alert_text' => $a->alert_text,
                'alert_link' => $a->alert_link,
                'created_at' => $a->created_at,
                'read_at' => $a->pivot->read_at,
            ]),
        ];
    }

    public function read(Request $request, UserAlert $alert)
    {
        $request->user()->alerts()->updateExistingPivot($alert->id, ['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $request)
    {
        $request->user()->alerts()->wherePivotNull('read_at')->get()
            ->each(fn ($a) => $request->user()->alerts()->updateExistingPivot($a->id, ['read_at' => now()]));

        return response()->noContent();
    }

    // ---- Admin "User Alerts" screen ----

    public function index(Request $request)
    {
        return TableQuery::respond(
            UserAlert::with('users:id,name'),
            $request,
            ['alert_text', 'alert_link'],
            ['id', 'alert_text', 'created_at'],
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alert_text' => 'required|string|max:255',
            'alert_link' => 'nullable|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        return response()->json(UserAlert::send($data['alert_text'], $data['alert_link'] ?? null, $data['user_ids'])->load('users:id,name'), 201);
    }

    public function destroy(UserAlert $alert)
    {
        $alert->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        UserAlert::whereIn('id', $request->validate(['ids' => 'required|array'])['ids'])->delete();

        return response()->noContent();
    }
}
