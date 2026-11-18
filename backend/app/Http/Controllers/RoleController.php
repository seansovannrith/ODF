<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        return TableQuery::respond(Role::with('permissions:id,title')->withCount('users'), $request, ['title', 'permissions.title'], ['id', 'title']);
    }

    public function show(Role $role)
    {
        return $role->load('permissions:id,title')->loadCount('users');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['title' => $data['title']]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return response()->json($role->load('permissions:id,title'), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        $role->update(['title' => $data['title']]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return $role->load('permissions:id,title');
    }

    public function destroy(Role $role)
    {
        abort_if($role->title === 'Admin', 422, 'The Admin role cannot be deleted.');
        $role->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        Role::whereIn('id', $request->validate(['ids' => 'required|array'])['ids'])
            ->where('title', '!=', 'Admin')->get()->each->delete();

        return response()->noContent();
    }

    protected function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role?->id)],
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);
    }
}
