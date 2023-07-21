<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\VoucherController as VoucherV2Controller;
use App\Http\Controllers\v2\NewsController as NewsV2Controller;

// Autenticação
Route::post("auth/login", "UserController@login")->withoutMiddleware('log.request');

// ################ Jornada de cliente - iframe ###################
Route::group(["prefix" => "journey"], function () {
    Route::get("/", "CustomersJourneyController@index");
    Route::post("/{id}/camera-analysis", "CustomersJourneyController@setCameraAnalysis");
    Route::post("/make-order", "CustomersJourneyController@makeOrder");
    Route::get("/details", "CustomersJourneyController@getOrderDetails");
    Route::get("/products", "CustomersJourneyController@productSearch");
    Route::get("/stores", "CustomersJourneyController@storeSearch");
    Route::get("/{id}/stolen-store", "CustomersJourneyController@getStolenStore");
    Route::put("/stolen-store/{id}", "CustomersJourneyController@updateStolenProduct");
    Route::delete("/stolen-store/{id}", "CustomersJourneyController@removeStolenProduct");
});

// ################ Customer - iframe ###################
Route::group(["prefix" => "customer"], function () {
    Route::get("/", "CustomerController@index");
    Route::get("scope/config", "CustomerController@configScreen");
    Route::get("scope/export", "CustomerController@export");
    Route::get("{id}", "CustomerController@edit");
    Route::get("{id}/cards", "CustomerController@showCards");
    Route::get("ban/reasons", "CustomerController@getReasons");
    Route::get("{id}/big-id", "CustomerController@getBigID");
    Route::get("{id}/webphone", "CustomerController@getWebphone");
    Route::get("{id}/whitelist/verify", "CustomerController@inWhitelist");
    Route::get("domains/all", "CustomerController@domains");
    Route::post("{id}/datavalid", "CustomerController@getDatavalidFace");
    Route::post("{id}/send-message", "CustomerController@sendFirebaseMessage");
    Route::post("{id}/validate-card", "CustomerController@makeTransactionToValidateCard");
    Route::post("search-by-face", "CustomerController@searchFace");
    Route::put("{id}/validate-card", "CustomerController@verifyCardResult");
    Route::put("{id}", "CustomerController@update");
    Route::put("{id}/ban", "CustomerController@ban");
    Route::put("{id}/unban", "CustomerController@unban");
    Route::put("{id}/activate", "CustomerController@activateClient");
    Route::put("{id}/whitelist/add", "CustomerController@addWhitelist");
    Route::delete("{id}", "CustomerController@delete");
});

Route::group(["middleware" => ["checkdomain", "auth:api", 'sync.permissions']], function () {

    // ################ Group without Permission Role ######################
    Route::group(['excluded_middleware' => ['log.request']], function () {
        Route::get("menu", "SettingController@menu");
        Route::post("auth/change_password", "UserController@changePassword");
        Route::get("media/{filename}", "MediaController@show");
    });

    // ################ Group Permission Role ######################
    Route::group(["middleware" => ["permission.roles"]], function () {
        // ################ Resources ##################
        Route::group([], function () {
            Route::resource("segments", "SegmentController");
            Route::resource("permissions", "PermissionController");
            Route::resource("payments", "PaymentController")->except(["show"]);
            Route::resource("usersFranchisee", "UserFranchiseeController");
            Route::resource('reasons', 'ReasonController')->except(["show"]);
            Route::resource("firebase-topic", "FirebaseTopicController")->except(["show"]);
        });

        // ################ Applications ###################
        Route::group(['prefix' => 'application'], function () {
            Route::resource('banners', 'BannerController');
            Route::resource('showcases', 'ShowcaseController')->except(["show"]);
            Route::get('banners/autocomplete/stores', 'BannerController@stores');

            // ################ showcase (whitelist) ###################
            Route::group(["prefix" => "showcases/whitelist"], function () {
                Route::get('groups', 'WhitelistController@groups');
                Route::get('{id}', 'WhitelistController@index');
                Route::post('{id}', 'WhitelistController@store');
                Route::put('{id}', 'WhitelistController@update');
                Route::delete('{id}', 'WhitelistController@destroy');
                Route::delete('{id}/all', 'WhitelistController@destroyAll');
            });
        });

        // ################ Company ###################
        Route::resource("companies", "CompanyController");
        Route::get("companies/{company_id}/domains", "CompanyController@domains");

        // // ################ Dashboard ###################
        Route::group(["prefix" => "dashboard"], function () {
            Route::get("/", "DashboardController@index");
            Route::get("autocomplete", "DashboardController@autoComplete");
            Route::get("new-clients", "DashboardController@newClients");
            Route::get("gross-revenue", "DashboardController@grossRevenue");

            Route::group(["prefix" => "billing-chart"], function () {
                Route::get("stores", "DashboardController@storeGraphic");
                Route::get("categories", "DashboardController@categoryGraphic");
                Route::get("cities", "DashboardController@citiesGraphic");
                Route::get("products", "DashboardController@productsGraphic");
                Route::get("clients", "DashboardController@clientsNumber");
                Route::get("resume", "DashboardController@resume");
                Route::get("filters", "DashboardController@filters");
            });
        });

        // ################ Deliveryman ###################
        Route::resource("delivery-man", "DeliveryManController");
        Route::group(["prefix" => "delivery-man"], function () {
            Route::put("{id}/repair", "DeliveryManController@repair");
            Route::put("{id}/suspend", "DeliveryManController@suspend");
            Route::put("{id}/refuse", "DeliveryManController@refuse");
            Route::put("{id}/preActive", "DeliveryManController@preActive");
            Route::get("{id}/hardwares", "DeliveryManController@hardwares");
            Route::post("{id}/hardwares", "DeliveryManController@hardwareStore");
            Route::put("{id}/hardwares/{hardware_id}", "DeliveryManController@hardwareUpdate");
            Route::delete("{id}/hardwares/{hardware_id}", "DeliveryManController@hardwareRemove");
            Route::get("{id}/regioes", "DeliveryManController@regioes");
        });

        // ################ Dispatch Log ###################
        Route::group(["prefix" => "dispatch_logs"], function () {
            Route::get("/filtered", "DispatchLogController@filtered");
            Route::get("/log/{id}", "DispatchLogController@getLog");
        });

        // ################ Activities ###################
        Route::resource('activities', "ActivityController")->except(["show"]);
        Route::post('activities/store_other_domain', 'ActivityController@storeOtherDomain');

        // ################ Domain ###################
        Route::resource("domains", "DomainController");
        Route::group(["prefix" => "domains"], function () {
            Route::get("{id}/features", "DomainController@features");
            Route::get("{id}/users", "DomainController@get_users");
            Route::put("{id}/users/{user_id}", "DomainController@update_user");
            Route::get("{id}/settings", "DomainController@show_settings");
            Route::post("{id}/upload_fonts", "DomainController@upload_fonts");
            Route::post("{id}/upload_logo", "DomainController@upload_logo");
            Route::post("{id}/upload_assets_app", "DomainController@upload_assets_app");
            Route::put("{id}/settings", "DomainController@update_settings");
            Route::put("{id}/features", "DomainController@update_feature");
        });

        // ################ Domain Settings ###################
        Route::get("files/domains", "FilesUploadController@domains");
        Route::group(["prefix" => "domains/"], function () {
            Route::get("{domainId}/files", "FilesUploadController@index");
            Route::post("{domainId}/files", "FilesUploadController@store");
            Route::delete("{domainId}/files", "FilesUploadController@destroy");
        });

        // ################ Feature ###################
        Route::resource("features", "FeatureController");
        Route::group(["prefix" => "features"], function () {
            Route::get("{id}/domains", "FeatureController@domainFeature");
            Route::put("{id}/domains/{domainId}", "FeatureController@domainFeatureEdit");
        });

        // ################ Feedback ###################
        Route::get("/feedback", "FeedbackController@index");

        // ################ Feed ###################
        Route::group(["prefix" => "feed", 'excluded_middleware' => ["permission.roles"]], function () {
            Route::get("/store", "FeedController@indexStoreFeed")->middleware('permission:view_feed');
            Route::get("/showcase", "FeedController@indexShowcase")->middleware('permission:view_feed');
            Route::get("{id}/store", "FeedController@ShowStoreFeed")->middleware('permission:view_feed');
            Route::get("{id}/showcase", "FeedController@ShowShowcase")->middleware('permission:view_feed');
            Route::put("{id}/store", "FeedController@updateStoreFeed")->middleware('permission:update_feed');
            Route::put("{id}/showcase", "FeedController@updateShowcase")->middleware('permission:update_feed');
            Route::put("{id}/products", "FeedController@updateStoreFeedProducts")->middleware('permission:update_feed');
            Route::put("/store", "FeedController@updateStoreFeedCollection")->middleware('permission:update_all_feed');
            Route::put("/showcase", "FeedController@updateShowcaseCollection")->middleware('permission:update_all_feed');
        });

        // ################ Franchise ###################
        Route::resource("franchise", "FranchiseController")->except(['show', 'destroy']);
        Route::group(["prefix" => "franchise"], function () {
            Route::get('report', 'FranchiseController@reportTransfers');
            Route::get('cities', 'FranchiseController@cities');
            Route::get('all', 'FranchiseController@list');
            Route::get('search/stores', 'FranchiseController@stores');
        });

        // ################ Invoice ###################
        Route::resource("invoices", "InvoiceController");
        Route::group(["prefix" => "invoices"], function () {
            Route::get("generate/items", "InvoiceController@generate");
            Route::post("{id}/process", "InvoiceController@process");
            Route::resource("{invoice_id}/items", "InvoiceItemController");
        });

        // ################ Job ###################
        Route::resource("jobs", "JobController")->only(['edit', 'create', 'index']);
        Route::post("jobs/{id}/call", "JobController@call");

        // ################ Loggers ###################
        Route::group(["prefix" => "loggers"], function () {
            Route::get("/", "LoggerController@index");
            Route::get("types", "LoggerController@types");
            Route::get("{type}/operations", "LoggerController@operation");
            Route::get("{id}/{type}", "LoggerController@detail")->where("id", "[0-9]+");
        });

        // ################ Log Printer ###################
        Route::group(["prefix" => "log-printer"], function () {
            Route::get("/", "LogPrinterController@index");
            Route::get("{id}", "LogPrinterController@logPrinter");
            Route::get("{id}/getLogPrinter", "LogPrinterController@getLogPrinter");
        });

        // ################ Map ###################
        Route::group(["prefix" => "mapa"], function () {
            Route::get("stores", "MapController@stores");
            Route::get("deliverymen", "MapController@deliverymen");
            Route::get("stats", "MapController@stats");
            Route::get("deliveries", "MapController@deliveries");
            Route::get("region", "MapController@region");
        });

        // ################ Menu ###################
        Route::resource("menus", "MenuController");
        Route::group(["prefix" => "menus"], function () {
            Route::get("tree", "MenuController@tree");
            Route::post("reorder", "MenuController@reorder");
        });

        // ################ News ###################
        Route::resource("news", "NewsController")->except(['show']);
        Route::get("news/regioes", "NewsController@regioes");
        Route::controller(NewsV2Controller::class)->prefix('v2/news')->group(function () {
            Route::get('/create', 'create');
            Route::get('/{id}/edit', 'edit');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::put('/{id}/status', 'updateStatus');
            Route::put("{id}/expire", "expire");
        });

        // ################ Showcase Groups ###################
        Route::group(['excluded_middleware' => ["permission.roles"], "prefix" => "showcase-groups"], function () {
            Route::post("/", "ShowcaseGroupsController@store")->middleware('permission:create_showcase_groups');
            Route::get('/autocomplete/showcases', 'ShowcaseGroupsController@showcases')->middleware('permission:create_showcase_groups');
            Route::get("/", "ShowcaseGroupsController@index")->middleware('permission:view_showcase_groups');
            Route::put("/{id}", "ShowcaseGroupsController@update")->middleware('permission:update_showcase_groups');
            Route::get("/{id}", "ShowcaseGroupsController@show")->middleware('permission:view_showcase_groups');
        });

        // ################ Groups ###################
        Route::group(['excluded_middleware' => ["permission.roles"], "middleware" => ["permission:manage_groups"]], function () {
            Route::resource("groups", "GroupController")->except(['update', 'edit']);
            Route::put("groups/{id}/status", "GroupController@changeStatus");
        });

        // ################ Online ###################
        Route::group(["prefix" => "operator"], function () {
            Route::post("online", "OnlineController@online");
            Route::post("offline", "OnlineController@offline");
        });

        // ################ Push ###################
        Route::resource("push", "PushNotificationController");
        Route::put("push/{id}/approve", "PushNotificationController@approve");

        // ################ Customer private ###################
        Route::group(["prefix" => "customer"], function () {
            Route::get("/{id}/credits", "LogCreditController@index");
            Route::post("/{id}/credits", "LogCreditController@store");
            Route::put("/credits/{id}", "LogCreditController@update");
            Route::post("/credits", "LogCreditController@storeMultiple");
            Route::get("/credits/history", "LogCreditController@history");
        });

        // ################ Retention ###################
        Route::group(["prefix" => "retentions"], function () {
            Route::get("/", "RetentionController@index");
            Route::get("/{id}", "RetentionController@retentions");
        });

        // ################ Role ###################
        Route::resource("roles", "RoleController")->only(['index', 'store', 'update', 'destroy']);
        Route::put("roles/{id}/childs", "RoleController@childs");

        // ################ Signature ###################
        Route::group(["prefix" => "signatures"], function () {
            Route::get("", "SignatureController@domain");
            Route::get("{id}/products", "SignatureController@products");
            Route::post("generate", "SignatureController@generateOrder");
        });

        // ################ Scale ###################
        Route::group(["prefix" => "scale"], function () {
            Route::get("/", "ScaleController@index");
            Route::post("/", "ScaleController@save");
            Route::get("servicedZone", "ScaleController@servicedZone");
            Route::post("addBonus", "ScaleController@addBonus");
            Route::post("delBonus", "ScaleController@delBonus");
            Route::put("{id}/deliveryMan", "ScaleController@deliveryMan");
            Route::get("deliverymen/available", "ScaleController@deliverymen");
            Route::delete("{id}", "ScaleController@destroy");
            Route::get("{id}/{entregador_id}/rel", "ScaleController@relDeliveryMan");
            Route::get("regions", "ScaleController@regions");
            Route::get("storesToTarget", "ScaleController@storesToTarget");
            Route::get("storesToSource", "ScaleController@storesToSource");
        });

        // ################ Search Correction ###################
        Route::group(["prefix" => "search_correction"], function () {
            Route::get("/", "SearchCorrectionController@index");
            Route::post("create", "SearchCorrectionController@create");
            Route::delete("{id}/delete", "SearchCorrectionController@delete");
            Route::put("{id}/update", "SearchCorrectionController@update");
        });

        // ################ Service Area ###################
        Route::resource("service-areas", "ServiceAreaController");
        Route::group(["prefix" => "service-areas"], function () {
            Route::get('/franchises/list', 'ServiceAreaController@getFranchises');
            Route::get('/firebase/list', 'ServiceAreaController@firebaseTopic');
            Route::get('/{id}/in-use', 'ServiceAreaController@verifyUse');
            Route::put('/{id}/status', 'ServiceAreaController@updateStatus');
        });

        // ################ Service Area ###################
        Route::resource("service-areas-distances", "ServiceAreaDistanceController");
        Route::get("service-areas-distances/{service_area_id}/list", "ServiceAreaDistanceController@list")
            ->where("service_area_id", "[0-9]+");

        // ################ Store ###################
        Route::group([], function () {
            Route::group(["prefix" => "stores"], function () {
                Route::get("/", "StoreController@index");
                Route::delete("{id}", "StoreController@destroy");
                Route::post("/", "StoreController@store");
                Route::post("/external-create", "StoreController@externalCreate");
                Route::get("{id}/edit", "StoreController@edit");
                Route::get("{id}/details", "StoreController@indexDetails");
                Route::get("categories", "StoreController@categories");
                Route::get("optionsDomain", "StoreController@optionsDomain");
                Route::get("autocomplete/cities", "StoreController@citiesAutocomplete");
                Route::put("{id}", "StoreController@update");
                Route::put("{id}/habilitate", "StoreController@habilitate");
                Route::put("{id}/approve", "StoreController@approveStore");
                Route::put("{id}/reprove", "StoreController@reproveStore");
                Route::post("{id}/auth-shopkeeper", "StoreController@authShopkeeper");

                Route::group(["prefix" => "concierge"], function () {
                    Route::get("edit", "StoreController@edit_concierge");
                    Route::post("create", "StoreController@create_concierge");
                    Route::post("clone", "StoreController@clone_concierge");
                    Route::put("update", "StoreController@update_concierge");
                });

                // integrations
                Route::group(['excluded_middleware' => ["permission.roles"], "middleware" => ["permission:manage_integrations"]], function () {
                    Route::get("{storeId}/integrations", "StoreIntegrationController@index");
                    Route::get("{storeId}/available-integrations", "StoreIntegrationController@appsAvailable");
                    Route::post("{storeId}/integrations", "StoreIntegrationController@AddStoreIntegration");
                    Route::put("{storeId}/integrations/", "StoreIntegrationController@UpdateStoreIntegration");
                    Route::put("{storeId}/integrations/status/", "StoreIntegrationController@ChangeStoreIntegration");
                    Route::delete("{storeId}/integrations/{integration}", "StoreIntegrationController@deleteStoreIntegration");
                });
            });

            // ################ Data Store ###################
            Route::group(["prefix" => "data-stores"], function () {
                Route::get("{storeId}/history", "StoreController@history");
                Route::post("recess", "StoreController@recess");
                Route::post("wall", "StoreController@wall");
                Route::post("nopartnersvix", "StoreController@nopartnersvix");
                Route::post("changeDisablePaymentPos", "StoreController@changeDisablePaymentPos");
                Route::get("getReasonsEnable/{store_id}", "StoreController@getReasonsEnable");
                Route::put("updateShopkeeper", "StoreController@updateShopkeeper");
                Route::get("getRetention", "StoreController@getRetention");
                Route::post("addRetention", "StoreController@addRetention");
                Route::put("removeRetention", "StoreController@removeRetention");
                Route::post("closeNextDay", "StoreController@closeNextDay");
                Route::post("returnOperation", "StoreController@returnOperation");
                Route::get("getScheduled", "StoreController@getScheduled");
                Route::post("postScheduled", "StoreController@postScheduled");
                Route::delete("deleteScheduled", "StoreController@deleteScheduled");
                Route::post("cloneProduct", "StoreController@cloneProduct");
                Route::get("getProductStore", "StoreController@getProductStore");
                Route::post("blockExtractStore", "StoreController@blockExtractStore");
                Route::delete("deleteProduct", "StoreController@deleteProduct");
                Route::delete("resetProductPassword ", "StoreController@resetProductPassword ");

                Route::group(['excluded_middleware' => ["permission.roles"], "middleware" => "permission:list_ban_deliveryman"], function () {
                    Route::get("/{id}/ban/deliverymen", "StoreController@getBanDeliveryman");
                    Route::get("/deliverymen/search", "StoreController@prepareBanDeliveryman");
                    Route::post("/{id}/ban/deliveryman", "StoreController@banDeliveryman");
                    Route::put("/{id}/unban/deliveryman", "StoreController@unbanDeliveryman");
                });
            });
        });

        Route::group(['excluded_middleware' => ["permission.roles"], "prefix" => "v2/stores"], function () {
            Route::post("/", "v2\StoreController@store")->middleware('permission:create_store');
            Route::get("/create", "v2\StoreController@create")->middleware('permission:create_store');

            Route::get("/{id}/edit", "v2\StoreController@edit")->middleware('permission:update_store');
            Route::put("/{id}", "v2\StoreController@update")->middleware('permission:update_store');

            Route::get("/{id}/settings", "v2\StoreController@settings")->middleware('permission:update_store_settings');
            Route::put("/{id}/settings", "v2\StoreController@updateSettings")->middleware('permission:update_store_settings');

            Route::get("/{id}/payments", "v2\StoreController@payments")->middleware('permission:update_store_payments');
            Route::put("/{id}/payments", "v2\StoreController@updatePayments")->middleware('permission:update_store_payments');

            Route::put("/shopkeeper/change", "v2\StoreController@updateShopkeeper")->middleware('permission:update_shopkeeper');
        });

        // ################ Setting ###################
        Route::resource("settings", "SettingController");
        Route::group(["prefix" => "settings"], function () {
            Route::get("{id}/{tag}", "SettingController@setting_using")->where("tag", "domain|product|shift|showcase|store|customer|user|zone");
            Route::get("{id}/{tag}/search", "SettingController@search_setting_target")->where("tag", "domain|product|shift|showcase|store|customer|user|zone");
            Route::get("{id}/{tag}/{target_id}", "SettingController@get_setting_target")->where("tag", "domain|product|shift|showcase|store|customer|user|zone")->where("target_id", "[0-9]+");
            Route::post("{id}/{tag}/{target_id}", "SettingController@set_setting_target")->where("tag", "domain|product|shift|showcase|store|customer|user|zone")->where("target_id", "[0-9]+");
            Route::put("{id}/{tag}/{target_id}", "SettingController@update_setting_target")->where("tag", "domain|product|shift|showcase|store|customer|user|zone")->where("target_id", "[0-9]+");
        });

        // ################ identifiers ###################
        Route::group(["prefix" => "identifiers"], function () {
            Route::get("/", "IdentifierController@index");
            Route::put("/{id}/update-whitelist", "IdentifierController@updateWhitelist");
        });

        // ################ User ###################
        Route::resource("users", "UserController")->except(["show"]);
        Route::group(["prefix" => "users"], function () {
            Route::get("me", "UserController@me");
            Route::put("{id}/recover_password", "UserController@recoverPassword");
            Route::get("{id}/roles", "UserController@roles");
            Route::put("{id}/roles", "UserController@updateRoles");
            Route::put("{id}/type", "UserController@updateUserType");
        });

        // ################ Voucher ###################
        Route::group(["prefix" => "vouchers"], function () {
            Route::resource("/", "VoucherController");
            Route::get("regioes", "VoucherController@regioes");
            Route::put("{id}/expire", "VoucherController@expire");
            Route::get("/{id}/stores-to-target", "VoucherController@storesToTarget");
            Route::get("/{id}/stores-to-source", "VoucherController@storesToSource");
            Route::get("/{id}/customers-to-target", "VoucherController@customersToTarget");
            Route::get("/{id}/customers-to-source", "VoucherController@customersToSource");
            Route::get("/{id}/edit", "VoucherController@edit");
            Route::get("/{id}/edit", "VoucherV2Controller@edit");
        });
        Route::controller(VoucherV2Controller::class)->prefix('v2/vouchers')->group(function () {
            Route::post('/', 'store');
            Route::get('/create', 'create');
            Route::get('/{id}', 'show');
            Route::get('/{id}/{action}', 'showList');
            Route::put('/{id}', 'update');
        });

        // ################ Orders ###################
        Route::group(["prefix" => "orders"], function () {
            Route::get("/", "OrderController@index");
            Route::get('filters', "OrderController@selectFilters");
            Route::get('export', "OrderController@export");
            Route::get('remake/reasons', "OrderController@reasonsToRemakeOrder");
            Route::get('{orderId}/canceled-reason', "OrderController@canceledReason");
            Route::get('/{orderId}/antifraudlog', "OrderController@antifraudlog");
            Route::get('/{orderId}/edit-schedule', "OrderController@getEditSchedule");
            Route::post('remake', "OrderController@remakeOrder");
            Route::post('accept-antifraud', "OrderController@acceptAntifraud");
            Route::post('reverse-transaction', "OrderController@reverseTransaction");
            Route::post('validate-card', "OrderController@validateCard");
            Route::post('consult-cpf', "OrderController@consultCpf");
            Route::put('/{orderId}/edit-schedule', "OrderController@editSchedule");
            Route::put('stop-order', "OrderController@stopOrder");
        });

        // ################ Integrations (OAuth Tokens) ###################
        Route::group(["prefix" => "integrations"], function () {
            Route::get("/", "IntegrationsController@index");
            Route::put("/{id}", "IntegrationsController@update");
        });

        // ################ Category ###################
        Route::resource('categories', 'CategoryController')->except(["show"]);
        Route::group(["prefix" => "categories"], function () {
            Route::get("/showcases", "CategoryController@showcases");
            Route::get("/{id}/stores-to-target", "CategoryController@storesToTarget");
            Route::get("/{id}/stores-to-source", "CategoryController@storesToSource");
        });

        // ################ Seller ###################
        Route::group(["prefix" => "sellers"], function () {
            Route::post("", "SellerController@store");
            Route::get("{id}", "SellerController@show");
            Route::put("{id}", "SellerController@update");
            Route::get("zoop/search", "SellerController@searchZoopSeller");
            Route::get("zoop/mccs", "SellerController@getMcc");
            Route::get("stores/search", "SellerController@searchStores");
            Route::put("{id}/link", "SellerController@link");
        });

        // ################ Statement ###################
        Route::put("statement/{id}", "StatementController@update");

        // ################ Sales ###################
        Route::group(["prefix" => "sales"], function () {
            Route::get("/", "SalesController@index");
            Route::get("/{id}", "SalesController@show");
            Route::get("/{id}/products", "SalesController@products");
            Route::get("/{id}/activities", "SalesController@activities");
            Route::get("/{id}/situation", "SalesController@getSituation");
            Route::get("/{id}/payment-history", "SalesController@getPaymentHistory");
            Route::get("/{id}/address", "SalesController@getCustomerAddress");
            Route::get("/{id}/reasons/cancel", "SalesController@reasonsToCancel");
            Route::put("/{orderId}/situation", "SalesController@updateSituation");
            Route::put("/{orderId}/address", "SalesController@updateAddress");
            Route::put("/{orderId}/cancel", "SalesController@cancelOrder");
            Route::put("/{orderId}/undo", "SalesController@undoOrder");
            Route::put("/{orderId}/send-shopkeeper", "SalesController@sendToShopkeeper");
            Route::group(['excluded_middleware' => ["permission.roles"]], function () {
                Route::put("/{orderId}/reversal", "SalesController@reversalTransaction")->middleware('permission:rollback_transaction');
                Route::put("/{orderId}/capture", "SalesController@captureTransaction")->middleware('permission:capture_transaction');
            });

            // order comment
            Route::post("/{orderId}/comment", "OrderCommentController@store");
            Route::put("/{orderId}/comment/{id}", "OrderCommentController@update");
            Route::delete("/{orderId}/comment/{id}", "OrderCommentController@destroy");
        });

        // ################ Config frete (em massa) ###################
        Route::group(["prefix" => "delivery-tax"], function () {
            Route::post("/multiple", "DeliveryTaxController@storeMultiple");
            Route::get("/history", "DeliveryTaxController@history");
        });

        // ################ Market Network ###################
        Route::group(["prefix" => "market-network"], function () {
            Route::get("/autocomplete", "MarketNetworkController@autocomplete");
        });

        // ################ Shopkeeper ###################
        Route::group(['excluded_middleware' => ["permission.roles"], "middleware" => ["permission:update_shopkeeper"], "prefix" => "shopkeeper"], function () {
            Route::get("/search/email", "ShopkeeperController@searchByEmail");
            Route::get("/search/store", "ShopkeeperController@searchByStore");
            Route::get("/store/{id}/original", "ShopkeeperController@getOriginal");
            Route::get("/{id}", "ShopkeeperController@show");
            Route::post("/", "ShopkeeperController@store");
        });
    });
});
