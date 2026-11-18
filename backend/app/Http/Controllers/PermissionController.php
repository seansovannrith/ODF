<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        return TableQuery::respond(Permission::query(), $request, ['title'], ['id', 'title']);
    }

    public function show(Permission $permission)
    {
        return $permission->load('roles:id,title');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:100|alpha_dash|unique:permissions,title']);

        return response()->json(Permission::create($data), 201);
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('permissions')->ignore($permission->id)]]);
        $permission->update($data);

        return $permission;
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        Permission::whereIn('id', $request->validate(['ids' => 'required|array'])['ids'])->get()->each->delete();

        return response()->noContent();
    }
}
