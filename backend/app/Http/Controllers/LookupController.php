<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Title;
use App\Models\User;
use Illuminate\Http\Request;

/** Everything the forms need for their dropdowns, in one call. */
class LookupController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = [
            'departments' => Department::orderBy('name')->get(['id', 'name', 'name_kh']),
            'titles' => Title::orderBy('name')->get(['id', 'name', 'name_kh', 'abbr', 'abbr_kh']),
            'positions' => Position::orderBy('name')->get(['id', 'name', 'name_kh', 'active']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'name_kh']),
            'organisations' => Organisation::orderBy('name')->get(['id', 'name', 'name_kh']),
            'document_types' => DocumentType::orderBy('id')->get(['id', 'value', 'name', 'name_kh']),
            'users' => User::with('title:id,name,name_kh', 'position:id,name,name_kh')
                ->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'name_kh', 'email', 'title_id', 'position_id']),
            // users who may be chosen as the Boss (final approval & signature)
            'approver_ids' => User::where('active', true)
                ->whereHas('roles.permissions', fn ($q) => $q->where('title', 'document_approve_access'))
                ->pluck('id'),
        ];

        if ($request->user()->can('user_management_access')) {
            $data['roles'] = Role::orderBy('title')->get(['id', 'title']);
            $data['permissions'] = Permission::orderBy('title')->get(['id', 'title']);
        }

        return $data;
    }
}
