<?php

namespace App\Http\Controllers;

use App\Jobs\ResetUsersCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Packk\Core\Models\Menu;
use App\Rules\Setting\ShowMenu;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        if (isset($request->length)) {
            return Menu::leftJoin('menus as mMenu', 'mMenu.id', '=', 'menus.menu_id')
                ->identic('menus.key_system', 'packkadmin')
                ->like('menus.key', $request->key)
                ->like('menus.description', $request->description)
                ->orderBy('menus.menu_id', 'asc')
                ->select(\DB::raw('
                        menus.*,
                        mMenu.description as menu_desc
                    '))
                ->with(['menu_roles', 'menuDisableDomainsObj'])
                ->simplePaginate($request->length);
        } else {
            return Cache::remember('all-menus', 7200, function() {
                return Menu::whereNull('link')->select(['id', 'description'])
                    ->identic('key_system', 'packkadmin')
                    ->orderBy('menu_id')->get();
            });
        }
    }

    public function tree(Request $request)
    {
        $roles = auth()->user()->rolesUser->pluck('role_id')->all();
        $domain_id = auth()->user()->domain_id;
        return ShowMenu::getMenu('tree', $roles, $domain_id);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'key' => 'required',
            'description' => 'required',
            'icon' => 'required',
            'order' => 'required',
            'roles' => 'required',
            'not_domains' => 'sometimes',
            'menu_id' => 'sometimes',
            'link' => 'sometimes',
        ]);

        if (empty($payload['menu_id'])) {
            $payload['menu_id'] = null;
        }
        if (empty($payload['link'])) {
            $payload['link'] = null;
        }
        $roles = $payload['roles'];
        unset($payload['roles']);

        $notDomains = $payload['not_domains'];
        unset($payload['not_domains']);

        $menu = Menu::create($payload);
        $menu->rolesObj()->sync($roles);
        $menu->menuDisableDomainsObj()->sync($notDomains);
        dispatch(new ResetUsersCache($roles, true));
        Cache::forget('all-menus');

        return response([
            'success' => true,
            'data' => $menu
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $request->validate([
            'key' => 'required',
            'description' => 'required',
            'icon' => 'required',
            'order' => 'required',
            'roles' => 'required',
            'not_domains' => 'sometimes',
            'menu_id' => 'sometimes',
            'link' => 'sometimes',
        ]);

        if (empty($payload['menu_id'])) {
            $payload['menu_id'] = null;
        }

        if (empty($payload['link'])) {
            $payload['link'] = null;
        }

        $roles = $payload['roles'];
        unset($payload['roles']);

        $notDomains = $payload['not_domains'];
        unset($payload['not_domains']);

        $menu = Menu::find($id);
        $menu->update($payload);

        $removedRoles = DB::table('menu_roles')->where('menu_id', $id)
            ->whereNotIn('role_id', $roles)->get()->pluck('role_id');

        $menu->rolesObj()->sync($roles);
        $menu->menuDisableDomainsObj()->sync($notDomains);

        dispatch(new ResetUsersCache($roles, true));
        dispatch(new ResetUsersCache($removedRoles, true));
        Cache::forget('all-menus');

        return response([
            'success' => true,
            'data' => $menu
        ]);
    }

    public function reorder(Request $request)
    {
        $json = json_decode($request->json, true);
        foreach ($json as $key => $value) {
            Menu::where('id', $value['id'])->update(['order' => $value['order'], 'menu_id' => $value['menu_id']]);
        }

        return response([
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $menu = Menu::find($id);
        $menu->rolesObj()->sync([]);
        $menu->menuDisableDomainsObj()->sync([]);
        $menu->delete();

        return response([
            'success' => true,
        ]);
    }
}