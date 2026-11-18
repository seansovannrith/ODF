<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Organisation;
use App\Models\Position;
use App\Models\Staff;
use App\Models\Title;
use App\Support\Permissions;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * One generic CRUD controller for every "Settings" screen:
 * /api/settings/{resource}  where resource is one of the keys in config().
 */
class SettingsController extends Controller
{
    protected function config(string $resource): array
    {
        $map = [
            'departments' => [
                'model' => Department::class,
                'search' => ['name', 'name_kh', 'abbr', 'abbr_kh', 'code', 'description'],
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'abbr' => 'nullable|string|max:50', 'abbr_kh' => 'nullable|string|max:50',
                    'code' => 'nullable|string|max:20', 'description' => 'nullable|string',
                ],
            ],
            'titles' => [
                'model' => Title::class,
                'search' => ['name', 'name_kh', 'abbr', 'abbr_kh', 'description'],
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'abbr' => 'nullable|string|max:50', 'abbr_kh' => 'nullable|string|max:50',
                    'description' => 'nullable|string',
                ],
            ],
            'positions' => [
                'model' => Position::class,
                'search' => ['name', 'name_kh', 'description'],
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'description' => 'nullable|string', 'active' => 'boolean',
                ],
            ],
            'categories' => [
                'model' => Category::class,
                'search' => ['name', 'name_kh', 'description'],
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'description' => 'nullable|string',
                ],
            ],
            'organisations' => [
                'model' => Organisation::class,
                'search' => ['name', 'name_kh', 'description'],
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'description' => 'nullable|string',
                ],
            ],
            'document-types' => [
                'model' => DocumentType::class,
                'search' => ['value', 'name', 'name_kh', 'description'],
                'rules' => fn ($id) => [
                    'value' => 'required|string|max:100|unique:document_types,value'.($id ? ",$id" : ''),
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'description' => 'nullable|string',
                ],
            ],
            'staff' => [
                'model' => Staff::class,
                'with' => ['title', 'position', 'department', 'user'],
                'search' => ['staff_code', 'name', 'name_kh', 'title.name', 'position.name', 'department.name'],
                'exact' => ['gender'],
                'rules' => fn ($id) => [
                    'staff_code' => 'required|string|max:20|unique:staff,staff_code'.($id ? ",$id" : ''),
                    'name' => 'required|string|max:255', 'name_kh' => 'nullable|string|max:255',
                    'gender' => 'required|in:Male,Female',
                    'title_id' => 'nullable|exists:titles,id', 'position_id' => 'nullable|exists:positions,id',
                    'department_id' => 'nullable|exists:departments,id', 'user_id' => 'nullable|exists:users,id',
                    'phone' => 'nullable|string|max:50',
                ],
            ],
            'app-settings' => [
                'model' => AppSetting::class,
                'search' => ['name', 'value', 'description'],
                'file' => 'photo',
                'rules' => fn ($id) => [
                    'name' => 'required|string|max:100|unique:app_settings,name'.($id ? ",$id" : ''),
                    'value' => 'nullable|string', 'description' => 'nullable|string',
                    'photo' => 'nullable|image|max:2048',
                ],
            ],
        ];

        abort_unless(isset($map[$resource]), 404, 'Unknown settings resource');

        return $map[$resource];
    }

    /** e.g. departments + create => "department_create" */
    protected function authorizeAction(string $resource, string $action): void
    {
        $prefix = Permissions::settings()[$resource] ?? null;
        abort_unless($prefix && request()->user()->can("{$prefix}_{$action}"), 403, 'You do not have permission for this action.');
    }

    public function index(Request $request, string $resource)
    {
        $this->authorizeAction($resource, 'access');
        $cfg = $this->config($resource);
        $query = $cfg['model']::query()->with($cfg['with'] ?? []);

        return TableQuery::respond(
            $query, $request, $cfg['search'],
            array_merge(['id'], $cfg['exact'] ?? [], array_filter($cfg['search'], fn ($c) => ! str_contains($c, '.'))),
            exact: $cfg['exact'] ?? [],
        );
    }

    public function show(string $resource, int $id)
    {
        $this->authorizeAction($resource, 'show');
        $cfg = $this->config($resource);

        return $cfg['model']::with($cfg['with'] ?? [])->findOrFail($id);
    }

    public function store(Request $request, string $resource)
    {
        $this->authorizeAction($resource, 'create');
        $cfg = $this->config($resource);
        $data = $this->fillFile($request, $cfg, $request->validate(($cfg['rules'])(null)));
        $row = $cfg['model']::create($data);

        return response()->json($row->load($cfg['with'] ?? []), 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->authorizeAction($resource, 'edit');
        $cfg = $this->config($resource);
        $row = $cfg['model']::findOrFail($id);
        $data = $this->fillFile($request, $cfg, $request->validate(($cfg['rules'])($id)), $row);
        $row->update($data);

        return $row->load($cfg['with'] ?? []);
    }

    public function destroy(string $resource, int $id)
    {
        $this->authorizeAction($resource, 'delete');
        $cfg = $this->config($resource);
        $cfg['model']::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request, string $resource)
    {
        $this->authorizeAction($resource, 'delete');
        $cfg = $this->config($resource);
        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        $cfg['model']::whereIn('id', $ids)->get()->each->delete();

        return response()->noContent();
    }

    /** Public: app name + logo for the login page and sidebar. */
    public function branding()
    {
        $logo = AppSetting::where('name', 'logo')->first();

        return [
            'app_name' => AppSetting::get('app_name', 'ODF'),
            'app_full_name' => AppSetting::get('app_full_name', 'Organization Document Flow'),
            'logo_url' => $logo && $logo->photo ? '/api/branding/logo' : null,
            'org_name_kh' => AppSetting::get('org_name_kh', 'មន្ទីរពេទ្យកាល់ម៉ែត'),
            'org_name_en' => AppSetting::get('org_name_en', 'HOPITAL Calmette'),
            'ministry_kh' => AppSetting::get('ministry_kh', 'ក្រសួងសុខាភិបាល'),
        ];
    }

    public function logo()
    {
        $logo = AppSetting::where('name', 'logo')->first();
        abort_unless($logo && $logo->photo && Storage::disk('public')->exists($logo->photo), 404);

        return Storage::disk('public')->response($logo->photo);
    }

    protected function fillFile(Request $request, array $cfg, array $data, $row = null): array
    {
        $field = $cfg['file'] ?? null;
        unset($data[$field ?? '__none']);
        if ($field && $request->hasFile($field)) {
            if ($row && $row->{$field}) {
                Storage::disk('public')->delete($row->{$field});
            }
            $data[$field] = $request->file($field)->store('settings', 'public');
        }

        return $data;
    }
}
