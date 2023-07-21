<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Rules\Domain\DoDomain;
use Packk\Core\Models\Domain;
use Packk\Core\Models\User;
use Packk\Core\Models\Role;
use Packk\Core\Models\DomainFeature;

class DomainController extends Controller
{
    /**
     * @OA\Get(
     *   path="/domains", operationId="index_domains",summary="list Domain",tags={"Domain"},
     *   @OA\Response(response=200,description="A list with Domain",
     *     @OA\JsonContent(
     *          type="array",@OA\Items(ref="#/components/schemas/StoreDomain")
     *     ),
     *   )
     * )
     */
    public function index(Request $request)
    {
        $id = $request->get('id', 'all');
        $id .= $request->hasFeature ? '.hasFeature' : '';

        return Cache::remember("domains.{$id}", 5400,
            function () use ($request) {
                $domains = Domain::identic('id', $request->id)->get();

                if ($request->hasFeature) {
                    $resp = [];
                    foreach ($domains as $domain) {
                        $temp = $domain->toArray();
                        $temp['feature'][$request->hasFeature] = $domain->hasFeature($request->hasFeature);
                        $resp[] = $temp;
                    }
                    $domains = $resp;
                }

                return $domains;
            });
    }

    /**
     * @OA\Post(
     *   path="/domains",operationId="store_domains",summary="store Domain",tags={"Domain"},
     *   @OA\RequestBody(
     *         description="Exemple to add to the Domain",required=true,@OA\JsonContent(ref="#/components/schemas/StoreDomain")
     *   ),
     *   @OA\Response(response=200,description="A list with Domains",
     *     @OA\JsonContent(
     *          ref="#/components/schemas/StoreDomain"
     *     ),
     *   )
     * )
     */
    public function store(Request $request)
    {
        $payload = $this->validate($request, Domain::storeRules());

        // Guarda usuário e senha
        $user = $payload['email'];
        $password = $payload['password'];

        // Remove do array payload
        unset($payload['email']);
        unset($payload['password']);

        $domain = Domain::create($payload);

        // Faz todo o processo de criação necessário para o domínio
        (new DoDomain)->execute($user, $password, $domain->id, 6);

        return $domain;
    }

    /**
     * @OA\Get(
     *   path="/domains/{DomainId}",operationId="show_domains",summary="list a Domain",tags={"Domain"},
     *   @OA\Parameter(
     *      name="DomainId",in="path",description="Domain id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A list with Domain",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/ShowDomain"
     *     ),
     *   )
     * )
     */
    public function show($id)
    {
        $Domain = Domain::findOrFail($id);
        return $Domain;
    }

    /**
     * @OA\Put(
     *   path="/domains/{DomainId}",operationId="update_domains",summary="update a Domain",tags={"Domain"},
     *   @OA\Parameter(
     *      name="DomainId",in="path",description="Domain id",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="A Domain",
     *     @OA\JsonContent(
     *         ref="#/components/schemas/UpdateDomain"
     *     ),
     *   )
     * )
     */
    public function update(Request $request, $id)
    {
        $payload = $this->validate($request, Domain::updateRules());
        $domain = Domain::where("id", $id)
            ->update($payload);
        return $domain;
    }

    /**
     * @OA\Delete(
     *   path="/domains/{DomainId}",operationId="destroy_domains",summary="destroy Domain",tags={"Domain"},
     *   @OA\Parameter(
     *      name="DomainId",in="path",description="Domain ID",required=true,@OA\Schema(type="integer")
     *   ),
     *   @OA\Response(response=200,description="deleted Domain",
     *   )
     * )
     */
    public function destroy($id)
    {
        try {
            $domain = Domain::findOrFail($id);
            $domain->delete();
            return response(true);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function show_settings($domain_id)
    {
        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {

            // Coleta os dados de configurações
            $objResposta = new \stdClass;
            $objResposta->font = $domain->getSetting('font');
            $objResposta->font_light = str_replace('\/', '/', $domain->getSetting('font_light'));
            $objResposta->font_bold = str_replace('\/', '/', $domain->getSetting('font_bold'));
            $objResposta->logo = str_replace('\/', '/', $domain->getSetting('logo'));
            $objResposta->primary_color = $domain->getSetting('primary_color');
            $objResposta->secondary_color = $domain->getSetting('secondary_color');
            $objResposta->text_accent_color = $domain->getSetting('text_accent_color');
            $objResposta->primary_dark_color = $domain->getSetting('primary_dark_color');
            $objResposta->sidemenu_background_color = $domain->getSetting('sidemenu_background_color');
            $objResposta->mobile_icon = $domain->getSetting('mobile_icon');
            $objResposta->background_splash = $domain->getSetting('background_splash');
            $objResposta->background_login = $domain->getSetting('background_login');
            $objResposta->indication_banner = $domain->getSetting('indication_banner');
            $objResposta->indication_text = $domain->getSetting('indication_text');
            $objResposta->first_access_banner = $domain->getSetting('first_access_banner');
            $objResposta->store_list_banner = $domain->getSetting('store_list_banner');
            $objResposta->disable_payment_pos = $domain->getSetting('disable_payment_pos');
            $objResposta->primary_category = $domain->getSetting('primary_category');
            $objResposta->secondary_category = $domain->getSetting('secondary_category');
            $objResposta->app_name = $domain->getSetting('app_name');
            $objResposta->app_resume_description = $domain->getSetting('app_resume_description');
            $objResposta->app_description = $domain->getSetting('app_description');

            $objResposta->logo_primary = $domain->getSetting('logo_primary');
            $objResposta->logo_secondary = $domain->getSetting('logo_secondary');
            $objResposta->homepage_image = $domain->getSetting('homepage_image');
            $objResposta->featured_section = $domain->getSetting('featured_section');
            $objResposta->dynamic_section = $domain->getSetting('dynamic_section');
            $objResposta->link_facebook = $domain->getSetting('link_facebook');
            $objResposta->link_twitter = $domain->getSetting('link_twitter');
            $objResposta->link_instagram = $domain->getSetting('link_instagram');
            $objResposta->link_youtube = $domain->getSetting('link_youtube');
            $objResposta->banner_header = $domain->getSetting('banner_header');
            $objResposta->banner_header_mobile = $domain->getSetting('banner_header_mobile');

            return response()->json($objResposta, 200);
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function update_settings(Request $request, $domain_id)
    {
        $payload = $this->validate($request, [
            "primary_color" => "nullable",
            "primary_dark_color" => "nullable",
            "secondary_color" => "nullable",
            "text_accent_color" => "nullable",
            "sidemenu_background_color" => "nullable",
            "primary_category" => "nullable",
            "secondary_category" => "nullable",
            "app_name" => "nullable",
            "app_resume_description" => "nullable",
            "app_description" => "nullable",
            "disable_payment_pos" => "sometimes",
            "logo_primary" => "nullable",
            "logo_secondary" => "nullable",
            "homepage_image" => "nullable",
            "featured_section" => "nullable",
            "dynamic_section" => "nullable",
            "link_facebook" => "nullable",
            "link_twitter" => "nullable",
            "link_instagram" => "nullable",
            "link_youtube" => "nullable",
            "banner_header" => "nullable",
            "banner_header_mobile" => "nullable"
        ]);

        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {
            if (isset($payload['featured_section'])) {
                $service = app('Packk\Core\Util\S3Directory');
                foreach ($payload['featured_section'] as $index => $item) {
                    if (isset($item['imageBase64'])) {
                        $fileName = time() . '_' . $item['image'];
                        $directory = $service->_prepareDirectory($domain_id, '' . $fileName);
                        $service->sendFileContent($directory, $item['imageBase64']);

                        unset($payload['featured_section'][$index]['imageBase64']);
                        $payload['featured_section'][$index]['image'] = $service->getPublicPath($directory);
                    }
                }
            }

            // Salva configurações
            $domain->setSetting('primary_color', (isset($payload['primary_color'])) ? $payload['primary_color'] : null);
            $domain->setSetting('secondary_color', (isset($payload['secondary_color'])) ? $payload['secondary_color'] : null);
            $domain->setSetting('primary_dark_color', (isset($payload['primary_dark_color'])) ? $payload['primary_dark_color'] : null);
            $domain->setSetting('text_accent_color', (isset($payload['text_accent_color'])) ? $payload['text_accent_color'] : null);
            $domain->setSetting('sidemenu_background_color', (isset($payload['sidemenu_background_color'])) ? $payload['sidemenu_background_color'] : null);
            $domain->setSetting('disable_payment_pos', (isset($payload['disable_payment_pos'])) ? $payload['disable_payment_pos'] : 0);
            $domain->setSetting('primary_category', (isset($payload['primary_category'])) ? $payload['primary_category'] : null);
            $domain->setSetting('secondary_category', (isset($payload['secondary_category'])) ? $payload['secondary_category'] : null);
            $domain->setSetting('app_name', (isset($payload['app_name'])) ? $payload['app_name'] : null);
            $domain->setSetting('app_resume_description', (isset($payload['app_resume_description'])) ? $payload['app_resume_description'] : null);
            $domain->setSetting('app_description', (isset($payload['app_description'])) ? $payload['app_description'] : null);

            $domain->setSetting('logo_primary', (isset($payload['logo_primary'])) ? $payload['logo_primary'] : null);
            $domain->setSetting('logo_secondary', (isset($payload['logo_secondary'])) ? $payload['logo_secondary'] : null);
            $domain->setSetting('homepage_image', (isset($payload['homepage_image'])) ? $payload['homepage_image'] : null);
            $domain->setSetting('featured_section', (isset($payload['featured_section'])) ? $payload['featured_section'] : null);
            $domain->setSetting('dynamic_section', (isset($payload['dynamic_section'])) ? $payload['dynamic_section'] : null);
            $domain->setSetting('link_facebook', (isset($payload['link_facebook'])) ? $payload['link_facebook'] : null);
            $domain->setSetting('link_twitter', (isset($payload['link_twitter'])) ? $payload['link_twitter'] : null);
            $domain->setSetting('link_instagram', (isset($payload['link_instagram'])) ? $payload['link_instagram'] : null);
            $domain->setSetting('link_youtube', (isset($payload['link_youtube'])) ? $payload['link_youtube'] : null);
            $domain->setSetting('banner_header', (isset($payload['banner_header'])) ? $payload['banner_header'] : null);
            $domain->setSetting('banner_header_mobile', (isset($payload['banner_header_mobile'])) ? $payload['banner_header_mobile'] : null);

            return response()->json($payload, 200);
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function upload_fonts(Request $request, $domain_id)
    {
        $payload = $this->validate($request, [
            'font_light' => 'required',
            'font_bold' => 'required'
        ]);

        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {

            try {
                // Faz uplod da fonte light
                $payload['font_light']->storeAs(
                    'domains/' . $domain_id . '/settings/', 'font_light.' . $payload['font_light']->getClientOriginalExtension(), 's3_packkbucket'
                );

                // Captura do S3 a URL do arquivo
                $url_light = Storage::disk('s3_packkbucket')->url('domains/' . $domain_id . '/settings/font_light.' . $payload['font_light']->getClientOriginalExtension());

                // Faz uplod da fonte bold
                $payload['font_bold']->storeAs(
                    'domains/' . $domain_id . '/settings/', 'font_bold.' . $payload['font_bold']->getClientOriginalExtension(), 's3_packkbucket'
                );

                // Captura do S3 a URL do arquivo
                $url_bold = Storage::disk('s3_packkbucket')->url('domains/' . $domain_id . '/settings/font_bold.' . $payload['font_bold']->getClientOriginalExtension());


                $domain->setSetting('font', 'font');
                $domain->setSetting('font_light', $url_light);
                $domain->setSetting('font_bold', $url_bold);

                return response()->json([
                    "message" => "Upload realizado com sucesso!",
                    "path" => $url_light,
                    "path_bold" => $url_bold
                ], 200);
            } catch (\Throwable $th) {
                throw $th;
            }
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function upload_logo(Request $request, $domain_id)
    {
        $payload = $this->validate($request, [
            'file' => 'required|dimensions:width=1024,height=1024|mimes:png'
        ]);

        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {
            try {
                // Faz o upload
                $payload['file']->storeAs(
                    'domains/' . $domain_id . '/settings/', 'logo.' . $payload['file']->getClientOriginalExtension(), "s3_packkbucket"
                );

                $url = Storage::disk('s3_packkbucket')->url('domains/' . $domain_id . '/settings/logo.' . $payload['file']->getClientOriginalExtension());
                $domain->setSetting('logo', $url);

                return response()->json([
                    "message" => "Upload realizado com sucesso!"
                ]);
            } catch (\Throwable $th) {
                throw $th;
            }
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function upload_assets_app(Request $request, $domain_id)
    {
        $payload = $this->validate($request, [
            'mobile_icon' => 'sometimes|required|dimensions:width=1024,height=1024|mimes:png|max:500',
            'background_splash' => 'sometimes|required|dimensions:width=608,height=1080|mimes:png|max:500',
            'background_login' => 'sometimes|required|dimensions:width=608,height=1080|mimes:png|max:500',
            'indication_banner' => 'sometimes|required|dimensions:width=700,height=350|mimes:png|max:500',
            'indication_text' => 'sometimes|required',
            'logo_primary' => 'sometimes|required',
            'logo_secondary' => 'sometimes|required',
            'banner_header' => 'sometimes|required',
            'banner_header_mobile' => 'sometimes|required',
            'homepage_image' => 'sometimes|required|dimensions:width=1200,height=880|mimes:png|max:500',
            'first_access_banner' => 'sometimes|required|dimensions:width=700,height=450|mimes:png|max:500',
            'store_list_banner' => 'sometimes|required|dimensions:width=1200,height=200|mimes:png|max:500'
        ], [
            'homepage_image.dimensions' => 'O tamanho deve ser 1200x880',
            'mobile_icon.dimensions' => 'O tamanho deve ser 1024x1024',
            'background_splash.dimensions' => 'O tamanho deve ser 608x1080',
            'background_login.dimensions' => 'O tamanho deve ser 608x1080',
            'indication_banner.dimensions' => 'O tamanho deve ser 700x350',
            'first_access_banner.dimensions' => 'O tamanho deve ser 700x450',
            'store_list_banner.dimensions' => 'O tamanho deve ser 1200x200',
            'max' => 'Tamanho arquivo deve ser até 500KB',
            'mimes' => 'Arquivo deve ter o formato PNG',
        ]);

        $domain = Domain::find($domain_id);

        if ($domain) {
            if (isset($payload['indication_text'])) {
                $domain->setSetting('indication_text', $payload['indication_text']);
                unset($payload['indication_text']);

                if (count($payload) == 0) {
                    return response()->json([
                        "message" => "Texto do banner indicativo atualizado com sucesso!"
                    ], 200);
                }
            }

            try {
                foreach ($payload as $key => $value) {
                    $payload[$key]->storeAs(
                        'domains/' . $domain_id . '/settings/', $key . '.' . $payload[$key]->getClientOriginalExtension(), "s3_packkbucket"
                    );

                    $url = Storage::disk('s3_packkbucket')->url('domains/' . $domain_id . '/settings/' . $key . '.' . $payload[$key]->getClientOriginalExtension());
                    $domain->setSetting($key, $url);
                }

                return response()->json([
                    "message" => "Upload(s) realizado(s) com sucesso!"
                ], 201);
            } catch (\Throwable $th) {
                throw $th;
            }
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function features($domain_id)
    {
        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {
            $features = \DB::table('features')
                ->select('features.*', 'domain_feature.enabled')
                ->leftJoin('domain_feature', function ($join) use ($domain_id) {
                    $join->on('features.id', '=', 'domain_feature.feature_id')
                        ->where('domain_feature.domain_id', '=', $domain_id);
                })->get();

            return response()->json($features, 200);
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    public function update_feature(Request $request, $domain_id)
    {
        $payload = $this->validate($request, [
            'id' => 'required',
            'enabled' => 'required'
        ]);

        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {
            $domainFeature = DomainFeature::updateOrCreate(
                [
                    'domain_id' => $domain_id,
                    'feature_id' => $payload['id']
                ],
                [
                    'enabled' => ($payload['enabled']) ? 1 : 0
                ]
            );

            return response()->json([
                "message" => "Funcionalidade atualizada no domínio",
            ], 200);
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    /**
     * Retorna todos os usuários de um domínio
     *
     * @param integer $domain_id
     * @param string $role
     * @return json
     */
    public function get_users(Request $request, $domain_id)
    {
        // Se foi passado a role como querystring
        $role = $request->has('role') ? $request->input('role') : "owner";

        $domain = Domain::where("id", $domain_id)->first();

        if ($domain) {
            $roleOwner = Role::where('label', $role)->firstOrFail();

            $ownerUsers = DB::table('users')
                ->join('role_user', 'users.id', '=', 'role_user.user_id')
                ->where('role_user.role_id', '=', $roleOwner->id)
                ->where('users.domain_id', '=', $domain_id)
                ->get();

            $ownerUsersCustom = $ownerUsers->map(function ($user) {
                return [
                    'id' => $user->user_id,
                    'nome' => $user->nome,
                    'email' => $user->email
                ];
            });

            return response()->json([
                "message" => count($ownerUsersCustom) > 0 ? "Usuários encontrados" : "Nenhum usuário encontrado",
                "data" => $ownerUsersCustom
            ], 200);
        } else {
            return response()->json([
                "message" => "Domínio não existe",
            ], 500);
        }
    }

    /**
     * Realiza a atualização de um usuário existente
     *
     * @param Request $request
     * @param integer $domain_id
     * @param integer $user_id
     * @return integer
     */
    public function update_user(Request $request, $domain_id, $user_id)
    {
        $payload = $this->validate($request, [
            'nome' => 'sometimes|required',
            'email' => 'sometimes|required',
            'password' => 'sometimes|required|min:8'
        ]);

        if (isset($payload['password'])) {
            $payload['password'] = bcrypt($payload['password']);
        }

        $user = User::withoutGlobalScope('App\Scopes\DomainScope')->where("id", $user_id)->update($payload);

        return $user;
    }
}