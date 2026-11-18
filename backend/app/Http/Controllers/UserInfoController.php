<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInfo;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "User Information": personal details of a user.
 * Title / position / department are stored on the user (the document flow uses them),
 * so this screen edits them on the user too.
 */
class UserInfoController extends Controller
{
    protected array $with = ['user:id,name,name_kh,email,title_id,position_id,department_id',
        'user.title:id,name,name_kh', 'user.position:id,name,name_kh', 'user.department:id,name,name_kh'];

    public function index(Request $request)
    {
        return TableQuery::respond(
            UserInfo::with($this->with),
            $request,
            ['gender', 'phone', 'place_of_birth', 'id_card_no', 'user.name', 'user.email'],
            ['id', 'gender', 'date_of_birth'],
            exact: ['gender'],
        );
    }

    public function show(UserInfo $userInfo)
    {
        return $userInfo->load($this->with);
    }

    public function store(Request $request)
    {
        [$info, $userFields] = $this->validated($request);
        $row = UserInfo::create($info);
        User::find($info['user_id'])->update($userFields);

        return response()->json($row->load($this->with), 201);
    }

    public function update(Request $request, UserInfo $userInfo)
    {
        [$info, $userFields] = $this->validated($request, $userInfo);
        $userInfo->update($info);
        User::find($info['user_id'])->update($userFields);

        return $userInfo->load($this->with);
    }

    public function destroy(UserInfo $userInfo)
    {
        $userInfo->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        UserInfo::whereIn('id', $request->validate(['ids' => 'required|array'])['ids'])->get()->each->delete();

        return response()->noContent();
    }

    protected function validated(Request $request, ?UserInfo $row = null): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::unique('user_infos')->ignore($row?->id)],
            'title_id' => 'nullable|exists:titles,id',
            'position_id' => 'nullable|exists:positions,id',
            'department_id' => 'nullable|exists:departments,id',
            'gender' => 'nullable|in:Male,Female',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'id_card_no' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $userFields = array_intersect_key($data, array_flip(['title_id', 'position_id', 'department_id']));

        return [array_diff_key($data, $userFields), $userFields];
    }
}
