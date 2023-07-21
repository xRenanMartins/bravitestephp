<?php

namespace App\Http\Controllers;

use App\Excel\Imports\GroupsImport;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Jobs\Groups\ProcessGroup;
use App\Response\ApiResponse;
use App\Rules\Group\NewGroup;
use App\Rules\Group\ShowGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Category;
use Packk\Core\Models\Group;
use Packk\Core\Models\GroupAssociation;

class GroupController extends Controller
{
    public function create()
    {
        return ApiResponse::sendResponse([
            'categories' => Category::where("tipo", 'L')->selectRaw("id, nome as name")->where("ativo", true)->get()->toArray(),
        ]);
    }

    public function index(Request $request)
    {
        return Group::query()
            ->identic('id', $request->id)
            ->identic('type', $request->type)
            ->identic('name', $request->name)
            ->identic('status', $request->status)
            ->orderByDesc('created_at')
            ->simplePaginate($request->length);
    }

    public function store(StoreGroupRequest $request, NewGroup $newGroup)
    {
        try {
            DB::beginTransaction();
            $payload = $request->all();

            if ($request->hasFile('file')) {
                $import = new GroupsImport;
                \Excel::import($import, $request->file);
                $payload['ids'] = $import->getRows();
            }

            $groups = $newGroup->execute($payload);
            DB::commit();
            dispatch(new ProcessGroup($groups));
            return ApiResponse::sendResponse($groups);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($e);
        }
    }

    public function destroy($id)
    {
        $groups = Group::findOrFail($id);
        GroupAssociation::where('group_id', $groups->id)->where('fixed', false)->delete();
        $groups->delete();
        return ApiResponse::sendResponse();
    }

    public function changeStatus(Request $request, $id)
    {
        $payload = $request->validate([
            "status" => "required|in:ACTIVE,INACTIVE"
        ]);

        $groups = Group::findOrFail($id);
        $groups->status = $payload['status'];
        $groups->save();

        return ApiResponse::sendResponse();
    }

    public function show(Request $request, $id, ShowGroup $showGroup)
    {
        try {
            $data = $showGroup->execute($id);
            return ApiResponse::sendResponse($data);
        } catch (ModelNotFoundException) {
            return ApiResponse::sendError('Registro não encontrado!');
        } catch (\Exception $exception) {
            return ApiResponse::sendUnexpectedError($exception);
        }
    }
}