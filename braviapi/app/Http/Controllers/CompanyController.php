<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Packk\Core\Models\Company;

class CompanyController extends Controller
{
    /**
     * @OA\Get(
     *   path="/companys", operationId="index_companys",summary="list Company",tags={"Company"},
     *   @OA\Response(response=200,description="A list with Company",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreCompany")
     *     ),
     *   )
     * )
     */
    public function index()
    {
        return Company::all();
    }

    /**
     * @OA\Post(
     *   path="/companys",operationId="store_companys",summary="store Company",tags={"Company"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Company",required=true,@OA\JsonContent(ref="#/components/schemas/StoreCompany")
     *   ),
     *   @OA\Response(response=200,description="A list with Companys",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreCompany"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, Company::storeRules());
        $company = Company::create($payload);
        return $company;
    }

    /**
     * @OA\Get(
     *   path="/companys/{CompanyId}",operationId="show_companys",summary="list a Company",tags={"Company"},
     *   @OA\Parameter(
     *      name="CompanyId",in="path",description="Company id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with Company",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowCompany"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        $Company = Company::findOrFail($id);
        return $Company;
    }

    /**
     * @OA\Put(
     *   path="/companys/{CompanyId}",operationId="update_companys",summary="update a Company",tags={"Company"},
     *   @OA\Parameter(
     *      name="CompanyId",in="path",description="Company id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Company",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateCompany"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Company::updateRules());
        $company = Company::where("id", $id)
                                ->update($payload);
        return $company;
    }

    /**
     * @OA\Delete(
     *   path="/companys/{CompanyId}",operationId="destroy_companys",summary="destroy Company",tags={"Company"},
     *   @OA\Parameter(
     *      name="CompanyId",in="path",description="Company ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Company",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $company = Company::findOrFail($id);
            $company->delete();
            return response(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function domains($company_id) {
        try {
            $company = Company::find($company_id);

            if ($company){
                return response()->json($company->domains, 200);
            }else{
                return response()->json([
                    'message' => 'Empresa ['.$company_id.'] não encontrado',
                ], 500);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
