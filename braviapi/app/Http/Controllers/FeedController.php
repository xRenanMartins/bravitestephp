<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateShowcaseFeedCollection;
use App\Jobs\UpdateStoreFeedCollection;
use App\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Packk\Core\Jobs\SendShopFeedEvent;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Mongo\ShowcaseFeed;
use Packk\Core\Models\Mongo\StoreFeed;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Store;

class FeedController extends Controller
{

    public function indexStoreFeed(Request $request)
    {
        $id = $request->id ? (int)$request->id : '';
        $name = $request->name;
        $length = $request->length ?? 10;

        return StoreFeed::query()
            ->select('id', 'name', 'domain_id', 'order', 'type', 'display_mode', 'status', 'is_enabled')
            ->like('name', $name)
            ->identic('id', $id)
            ->orderBy('created_at', 'desc')
            ->simplePaginate($length);
    }

    public function indexShowcase(Request $request)
    {
        $id = $request->id ? (int)$request->id : '';
        $identifier = $request->identifier;
        $length = $request->length ?? 10;

        return ShowcaseFeed::query()
            ->select('id', 'identifier', 'title', 'order', 'type', 'style', 'is_active')
            ->like('identifier', $identifier)
            ->identic('id', $id)
            ->orderBy('created_at', 'desc')
            ->simplePaginate($length);
    }

    public function showStoreFeed($id)
    {
        $store = StoreFeed::query()->where('id', (int)$id)->first();
        return ApiResponse::sendResponse($store);
    }

    public function showShowcase($id)
    {
        $showcase = ShowcaseFeed::query()->where('id', (int)$id)->first();
        return ApiResponse::sendResponse($showcase);
    }

    public function updateStoreFeed($id)
    {
        $store = Store::find($id);
        if (!empty($store)) {
            dispatch(new SendShopFeedEvent($id, 'store.update'));
        } else {
            dispatch(new SendShopFeedEvent($id, 'store.destroy'));
        }

        return ApiResponse::sendResponse();
    }

    public function updateShowcase($id)
    {
        $showcase = Showcase::find($id);
        if (!empty($showcase)) {
            dispatch(new SendShowcaseFeedEvent($id, 'showcase.update'));
        } else {
            dispatch(new SendShowcaseFeedEvent($id, 'showcase.destroy'));
        }
        return ApiResponse::sendResponse();
    }

    public function updateStoreFeedCollection(Request $request)
    {
        $command = match ($request->update) {
            'whitelist' => 'shopFeed:update.whitelist',
            'promotions' => 'shopFeed:update.promotions',
            'hours' => 'shopFeed:update.scheduling',
            'addresses' => 'shopFeed:update.address',
            'categories' => 'shopFeed:update.category',
            default => "shopFeed:init",
        };

        dispatch(function () use($command) {
            Artisan::call($command);
        });
        return ApiResponse::sendResponse();
    }

    public function updateShowcaseCollection()
    {
        dispatch(function () {
            Artisan::call("showcase:create");
        });
        return ApiResponse::sendResponse();
    }

    public function updateStoreFeedProducts(Request $request, $id)
    {
        dispatch(new SendShopFeedEvent($id, 'product:update.all'));
        return ApiResponse::sendResponse();
    }
}