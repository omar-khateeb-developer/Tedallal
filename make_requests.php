<?php
$controllers = [
    'Api/Admin/MenuCategoryController.php' => ['StoreMenuCategoryRequest', 'UpdateMenuCategoryRequest'],
    'Api/Admin/MenuItemController.php' => ['StoreMenuItemRequest', 'UpdateMenuItemRequest'],
    'Api/Admin/OrderController.php' => [null, 'UpdateOrderRequest'],
    'Api/Admin/PromoCodeController.php' => ['StorePromoCodeRequest', 'UpdatePromoCodeRequest'],
    'Api/Admin/RestaurantController.php' => [null, 'UpdateRestaurantSettingsRequest'],
    'Api/Admin/TableController.php' => ['StoreTableRequest', 'UpdateTableRequest'],
    'Api/Admin/UserController.php' => ['StoreAdminUserRequest', 'UpdateAdminUserRequest'],
    'Api/SuperAdmin/RestaurantController.php' => ['StoreSuperRestaurantRequest', 'UpdateSuperRestaurantRequest'],
    'Api/SuperAdmin/UserController.php' => ['StoreSuperUserRequest', 'UpdateSuperUserRequest'],
    'Api/Public/CartController.php' => ['AddToCartRequest', 'UpdateCartRequest'],
    'Api/Public/OrderController.php' => ['StorePublicOrderRequest', null],
    'Api/Public/PromoCodeController.php' => ['ApplyPromoCodeRequest', null],
    'Api/Public/SessionController.php' => ['StartSessionRequest', null],
];

foreach ($controllers as $file => $requests) {
    if ($requests[0]) {
        exec("php artisan make:request {$requests[0]}");
    }
    if ($requests[1]) {
        exec("php artisan make:request {$requests[1]}");
    }
}
echo "Done.\n";
