<?php

namespace App\Rules\Setting;

use Illuminate\Support\Facades\Cache;
use Packk\Core\Models\Menu;

class ShowMenu
{
    public function execute()
    {
        $user = auth()->user();
        $domain_id = $user->domain_id;

        return Cache::remember("user.{$user->id}.menu", 3600,
            function () use ($user, $domain_id) {
                $roles = $user->rolesUser->pluck('role_id')->all();
                return self::getMenu('menu', $roles, $domain_id);
            });
    }

    public static function getMenu($type, $roles = null, $domain_id = null, $key_system = 'packkadmin')
    {
        $query = Menu::orderBy('order', 'asc')->whereNull('menu_id')->where('key_system', $key_system);
        if (is_null($roles) || in_array(2, $roles) || in_array(10, $roles)) {
            $menus = $query->get();
        } else {
            $menus = $query->rolesDomains($roles, $domain_id)->get();
        }
        return self::prepareMenu($menus, $type, $roles, $domain_id, $key_system);
    }

    private static function prepareMenu($menus, $type = 'tree', $roles = null, $domain_id = null, $key_system = 'packkadmin')
    {
        $menuArray = [];
        foreach ($menus as $value) {
            $query = $value->subMenus()->where('key_system', $key_system)->orderBy('order', 'asc');
            if (is_null($roles) || in_array(2, $roles) || in_array(10, $roles)) {
                $subMenus = $query->get();
            } else {
                $subMenus = $query->rolesDomains($roles, $domain_id)->get();
            }

            if (count($subMenus) > 0) {
                $resp = self::prepareMenu($subMenus, $type, $roles, $domain_id, $key_system);
                $temp = self::menuType($value, $resp, $type);
                $menuArray[] = $temp;
            } else {
                $temp = self::menuType($value, null, $type);
                $menuArray[] = $temp;
            }
        }

        return $menuArray;
    }

    private static function menuType($item, $children = null, $type = 'tree')
    {
        if ($type == 'tree') {
            $resp = [
                'id' => $item->id,
                'key' => $item->key,
                'menu_id' => $item->menu_id,
                'label' => $item->description,
                'data' => $item->description,
                'link' => $item->link,
                'order' => $item->order,
                'icon' => $item->icon,
            ];
            if ($children != null) {
                $resp['children'] = $children;
            }
            return $resp;
        } else {
            return [
                'id' => $item->id,
                'key' => $item->key,
                'label' => $item->description,
                'icon' => $item->icon,
                'routerLink' => $item->link,
                'items' => $children,
            ];
        }
    }
}
