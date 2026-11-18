<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected array $with = ['title', 'position', 'department', 'roles:id,title', 'staff:id,user_id,staff_code,name,name_kh'];

    public function index(Request $request)
    {
        $query = User::with($this->with);
        if ($roleId = $request->query('role_id')) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId));
        }

        return TableQuery::respond(
            $query,
            $request,
            ['name', 'name_kh', 'username', 'email', 'position.name', 'department.name', 'staff.staff_code', 'staff.name'],
            ['id', 'name', 'name_kh', 'username', 'email', 'email_verified_at', 'two_factor_enabled', 'active'],
        );
    }

    public function show(User $user)
    {
        return $user->load($this->with);
    }

    public function store(Request $request)
    {
        [$data, $roles] = $this->validated($request);
        $user = User::create($data);
        $user->roles()->sync($roles);

        return response()->json($user->load($this->with), 201);
    }

    public function update(Request $request, User $user)
    {
        [$data, $roles] = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (! ($data['two_factor_enabled'] ?? $user->two_factor_enabled)) {
            $data['two_factor_secret'] = null; // admin can switch off two-factor for a user who lost the phone
        }
        $user->update($data);
        $user->roles()->sync($roles);

        return $user->load($this->with);
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account here.');
        $user->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $ids = collect($request->validate(['ids' => 'required|array'])['ids'])
            ->reject(fn ($id) => (int) $id === $request->user()->id);
        User::whereIn('id', $ids)->get()->each->delete();

        return response()->noContent();
    }

    /** Streams a signature only to its owner or a participant in one of their documents. */
    public function signature(Request $request, User $user)
    {
        $viewer = $request->user();
        $canView = $viewer->id === $user->id;

        if (! $canView && $viewer->can('document_show')) {
            $documents = \App\Models\Document::whereHas('comments', fn ($query) => $query->where('user_id', $user->id));
            if (! $viewer->seesAllDocuments()) {
                $documents->where(fn ($query) => $query->where('created_by', $viewer->id)
                    ->orWhereHas('comments', fn ($comments) => $comments->where('user_id', $viewer->id)));
            }
            $canView = $documents->exists();
        }

        abort_unless($canView, 403);
        abort_unless($user->signature && Storage::disk('local')->exists($user->signature), 404);

        return Storage::disk('local')->response($user->signature);
    }

    /** Streams a user's profile photo to authenticated clients. */
    public function photo(User $user)
    {
        abort_unless($user->photo && Storage::disk('local')->exists($user->photo), 404);

        return Storage::disk('local')->response($user->photo);
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'roles' => 'required|array|min:1',
            'roles.*' => 'integer|exists:roles,id',
            'title_id' => 'nullable|exists:titles,id',
            'position_id' => 'nullable|exists:positions,id',
            'department_id' => 'nullable|exists:departments,id',
            'phone' => 'nullable|string|max:50',
            'active' => 'boolean',
            'email_verified' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ]);

        $roles = $data['roles'];
        unset($data['roles']);

        if (array_key_exists('email_verified', $data)) {
            $data['email_verified_at'] = $data['email_verified'] ? ($user?->email_verified_at ?? now()) : null;
            unset($data['email_verified']);
        }
        // Two-factor can only be switched OFF by an admin; switching on is done by the user in the profile.
        if (isset($data['two_factor_enabled']) && $data['two_factor_enabled'] && ! $user?->two_factor_enabled) {
            unset($data['two_factor_enabled']);
        }

        return [$data, $roles];
    }
}
