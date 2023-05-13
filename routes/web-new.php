<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});



$router->group(['prefix' => 'api'], function () use ($router) {


    /* 
     * Affiliate(User) API
     */
    $router->post('AffiliateRegister', ['uses' => 'User\UserController@AffiliateRegister']);
    $router->post('AffiliateVerify', ['uses' => 'User\UserController@verifyUser']);
    $router->post('AffiliateLogin', ['uses' => 'User\UserController@loginAffiliate']);
    $router->post('AffiliateResetPassword', ['uses' => 'User\UserController@AffiliateResetPassword']);
    $router->post('AffiliateChangePassword', ['uses' => 'User\UserController@affiliateChangePassword']);
    $router->post('AffiliateEdit', ['uses' => 'User\UserController@AffiliateEdit']);
    $router->post('AffiliateUpdate', ['uses' => 'User\UserController@AffiliateUpdate']);
    // Bank details
    $router->post('AffiliateEditBankDetails', ['uses' => 'User\UserController@AffiliateEditBankDetails']);
    $router->post('AffiliateUpdateBankDetails', ['uses' => 'User\UserController@AffiliateUpdateBankDetails']);
    // Ad
    $router->post('AffiliateAdList', ['uses' => 'User\AffiliateAdController@AffiliateAdList']);

    // Campaign
    $router->post('CampaignAdd', ['uses' => 'User\CampaignController@CampaignAdd']);
    $router->post('CampaignView', ['uses' => 'User\CampaignController@CampaignView']);
    $router->post('CampaignEdit', ['uses' => 'User\CampaignController@CampaignEdit']);
    $router->post('CampaignUpdate', ['uses' => 'User\CampaignController@CampaignUpdate']);
    $router->post('CampaignDelete', ['uses' => 'User\CampaignController@CampaignDelete']);
    $router->post('CampaignList', ['uses' => 'User\CampaignController@CampaignList']);

    /* 
     * Common Master tables
     */
    $router->get('CountryList', ['uses' => 'Master\CountryController@CountryList']);
    $router->get('AdBrandList', ['uses' => 'Master\AdController@AdBrandList']);
    $router->get('AdSizeList', ['uses' => 'Master\AdController@AdSizeList']);
    $router->get('AdTypeList', ['uses' => 'Master\AdController@AdTypeList']);
    $router->get('CampaignTypeList', ['uses' => 'Master\CampaignController@CampaignTypeList']);
    $router->get('CommissionTypeList', ['uses' => 'Master\CommissionController@CommissionTypeList']);
    $router->get('LanguageList', ['uses' => 'Master\LanguageController@LanguageList']);
    $router->get('MenuList', ['uses' => 'Master\MenuController@MenuList']);
    $router->get('TicketTypeList', ['uses' => 'Master\TicketTypeController@TicketTypeList']);
    $router->get('RevenueTypeList', ['uses' => 'Master\RevenueTypeController@RevenueTypeList']);
    $router->get('PaymentTypeList', ['uses' => 'Master\PaymentTypeController@PaymentTypeList']);
    $router->get('RoleList', ['uses' => 'Master\RoleController@RoleList']);
    $router->get('CurrencyList', ['uses' => 'Master\CurrencyController@CurrencyList']);
    

    /*  For All type of Users  */
    $router->post('auth/reset_password', ['uses' => 'Admin\AdminController@reset_password']);
    $router->post('Change_password/',['uses'=>'Admin\AdminController@Change_password']);

    /* Admin Add,Edit,Update,Delete,Active,Deactive Advertisment */
    $router->post('admin/Add_adds', ['uses' => 'Admin\AddController@Add_adds']);
    $router->get('admin/show_adds', ['uses' => 'Admin\AddController@show_adds']);
    $router->get('admin/show_single_adds/{id}', ['uses' => 'Admin\AddController@show_single_adds']);
    $router->get('admin/Edit_single_adds/{id}', ['uses' => 'Admin\AddController@Edit_single_adds']);
    $router->post('admin/Update_adds', ['uses' => 'Admin\AddController@Update_adds']);
    $router->get('admin/Delete_single_adds/{id}', ['uses' => 'Admin\AddController@Delete_single_adds']);


    /* Admin Add,Edit,Update,Delete,Active,Deactive News */
    $router->post('admin/Add_news', ['uses' => 'Admin\NewsController@Add_news']);
    $router->get('admin/show_news', ['uses' => 'Admin\NewsController@show_news']);
    $router->get('admin/show_single_news/{id}', ['uses' => 'Admin\NewsController@show_single_news']);
//    $router->get('admin/Edit_single_adds/{id}', ['uses' => 'Admin\AddController@Edit_single_adds']);
//    $router->post('admin/Update_adds', ['uses' => 'Admin\AddController@Update_adds']);
//    $router->get('admin/Delete_single_adds/{id}', ['uses' => 'Admin\AddController@Delete_single_adds']);


    $router->get('admin/Show_affilite', ['uses' => 'Admin\AddController@Show_affilite']);

    /* Admin login,logout,show subadmin */
    $router->post('auth/admin/login', ['uses' => 'Admin\AdminController@authenticate']);
    $router->get('logged_user', ['uses'=>'Admin\AdminController@logged_user']);
    $router->post('logged_out', ['uses'=>'Admin\AdminController@logged_out']);
    $router->post('admin/Show_subadmin', ['uses' => 'Admin\AdminController@Show_subadmin']);

    /* Admin Add,Edit,Update menu */
    $router->post('admin/Add_menu', ['uses' => 'Admin\AdminController@Add_menu']);
    $router->get('admin/Edit_menu/{menu_id}', ['uses' => 'Admin\AdminController@Edit_menu']);
    $router->post('admin/Update_menu', ['uses' => 'Admin\AdminController@Update_menu']);

    /* Admin Add,Edit,Update Subadmin with permission */
    $router->post('admin/Add_subadmin', ['uses' => 'Admin\AdminController@Add_subadmin']);
    $router->post('admin/Edit_subadmin/', ['uses' => 'Admin\AdminController@Edit_subadmin']);
    $router->post('admin/Update_subadmin', ['uses' => 'Admin\AdminController@Update_subadmin']);
    $router->post('admin/Delete_subadmin/', ['uses' => 'Admin\AdminController@Delete_subadmin']);





   // $router->post('auth/subadmin/login', ['uses' => 'SubAdmin\SubAdminController@subauthenticate']);
    $router->get('subadmin/Userpermission', ['uses' => 'SubAdmin\SubAdminController@Userpermission']);

    $router->post('ChangePassword/', ['uses' => 'Admin\AdminController@ChangePassword']);
});
