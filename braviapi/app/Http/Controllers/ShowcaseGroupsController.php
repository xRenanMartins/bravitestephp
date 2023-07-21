<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowcaseGroups\StoreShowcaseGroupRequest;
use App\Http\Requests\ShowcaseGroups\UpdateShowcaseGroupRequest;
use App\Response\ApiResponse;
use App\Rules\ShowcaseGroup\ListShowcaseGroup;
use Illuminate\Http\Request;
use App\Rules\ShowcaseGroup\V1\StoreShowcaseGroup;
use App\Rules\ShowcaseGroup\V1\UpdateShowcaseGroup;
use Exception;
use Illuminate\Support\Facades\DB;
use Packk\Core\Exceptions\CustomException;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\ShowcaseGroup;

class ShowcaseGroupsController extends Controller
{
    public function index(Request $request, ListShowcaseGroup $listShowcaseGroup)
    {
        try {
            return ApiResponse::sendResponse($listShowcaseGroup->execute($request));
        } catch (Exception $exception) {
            return ApiResponse::sendUnexpectedError($exception);
        }
    }

    public function store(StoreShowcaseGroupRequest $request, StoreShowcaseGroup $storeShowcaseGroup)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $data = $storeShowcaseGroup->execute($payload);
            DB::commit();

            return ApiResponse::sendResponse($data);
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }


    public function update(UpdateShowcaseGroupRequest $request, $id, UpdateShowcaseGroup $updateShowcaseGroup)
    {
        $payload = $request->validated();
        try {
            DB::beginTransaction();
            $data = $updateShowcaseGroup->execute($payload, $id);
            DB::commit();

            return ApiResponse::sendResponse($data);
        } catch (CustomException $e) {
            DB::rollBack();
            return ApiResponse::sendError($e->getMessage());
        } catch (\Exception $ex) {
            DB::rollBack();
            return ApiResponse::sendUnexpectedError($ex);
        }
    }


    public function show(Request $request, $id)
    {
        $data = ShowcaseGroup::query()->select('*')->with(['showcases' => function ($q) use ($request) {
            $q->identic('vitrines.id', $request->query('id'))->orLike('vitrines.identifier', $request->query('id'));
            $q->identic('vitrines.ativo', $request->active);
            $q->selectRaw('CONCAT(vitrines.id, " - ", vitrines.identifier) as showcases, vitrines.ativo');
        }])->where('id', $id)->first();
        return ApiResponse::sendResponse($data);
    }

    public function showcases(Request $request)
    {
        return Showcase::query()
            ->where(function ($q) use ($request) {
                $q->identic('id', $request->search)->orLike('identifier', $request->search);
            })->selectRaw('CONCAT(id, " - ", identifier) as showcase')->where('domain_id', 1)
            ->limit(10)->orderBy('identifier')->get()->pluck('showcase')->toArray();
    }
}
