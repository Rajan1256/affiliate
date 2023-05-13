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
use Illuminate\Support\Facades\Artisan;

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->get('/clearcache', function () use ($app) {
    Artisan::call('cache:clear');
    return "Cache is cleared";
});

$app->group(['prefix' => 'api'], function () use ($app) {
    /* 
     * Affiliate(User) API
     */
    $app->post('AffiliateRegister', ['uses' => 'User\UserController@AffiliateRegister']);
    $app->post('AffiliateVerify', ['uses' => 'User\UserController@verifyUser']);
    $app->post('AffiliateLogin', ['uses' => 'User\UserController@loginAffiliate']);
    $app->post('IsValidLoginToken', ['uses' => 'User\UserController@IsValidLoginToken']);
    $app->post('AffiliateResetPassword', ['uses' => 'User\UserController@AffiliateResetPassword']);
    $app->post('AffiliateChangePassword', ['uses' => 'User\UserController@affiliateChangePassword']);
    $app->post('GetAffiliateDetailByUserId', ['uses' => 'User\UserController@GetAffiliateDetailByUserId']);
    $app->post('GetAffiliateDetailsTreeView', ['uses' => 'User\UserController@GetAffiliateDetailsTreeView']);
    $app->post('UpdateProfileDetail', ['uses' => 'User\UserController@UpdateProfileDetail']);
    // Bank details
    $app->post('GetPaymentDetailByUserId', ['uses' => 'User\UserController@GetPaymentDetailByUserId']);
    $app->post('UpdatePaymentDetail', ['uses' => 'User\UserController@UpdatePaymentDetail']);
    // Sub Affiliate list with filter
    $app->post('ShowSubAffiliate', ['uses' => 'User\UserController@ShowSubAffiliate']);
    $app->post('UserSubAffiliateList', ['uses' => 'User\UserController@UserSubAffiliateList']);
    $app->post('SubAffiliateTree', ['uses' => 'User\UserController@SubAffiliateTree']);
    $app->post('SubAffiliateList', ['uses' => 'User\UserController@SubAffiliateList']);
    $app->post('AffiliateValidateToken', ['uses' => 'User\UserController@AffiliateValidateToken']);
    $app->post('GeneralStatisticsAffiliate', ['uses' => 'User\UserController@GeneralStatisticsAffiliate']);
    $app->post('CampaignStatisticsAffiliate', ['uses' => 'User\UserController@CampaignStatisticsAffiliate']);
    $app->post('CampaignTopFivePerforming', ['uses' => 'User\UserController@CampaignTopFivePerforming']);
    $app->post('MonthlyRevenueByRevenueType', ['uses' => 'User\UserController@MonthlyRevenueByRevenueType']);
    $app->post('TopPerformingAds', ['uses' => 'User\UserController@TopPerformingAds']);
    $app->post('RevenuePerRevenueModel', ['uses' => 'User\UserController@RevenuePerRevenueModel']);
    $app->post('RevenuePerCountry', ['uses' => 'User\UserController@RevenuePerCountry']);
    $app->post('AffiliateRevenueList', ['uses' => 'User\UserController@AffiliateRevenueList']);

    // Ad List for Campaign, show list in Ad Campaign time
    $app->post('GetAdsList', ['uses' => 'User\AffiliateAdController@GetAdsList']);
    $app->post('GetAdsList2', ['uses' => 'User\AffiliateAdController@GetAdsList2']);
    // Ad List for Campaign, show list in Ad Campaign time
    $app->post('AdListCampaign', ['uses' => 'User\AffiliateAdController@AdListCampaign']);

    // Campaign
    $app->post('CampaignRevenueType', ['uses' => 'User\CampaignController@CampaignRevenueType']);
    $app->post('CampaignAddUpdate', ['uses' => 'User\CampaignController@CampaignAddUpdate']);
    $app->post('CampaignAddUpdate2', ['uses' => 'User\CampaignController@CampaignAddUpdate2']);
    $app->post('CreateCampaignType', ['uses' => 'User\CampaignController@CreateCampaignType']);
    $app->post('CampaignView', ['uses' => 'User\CampaignController@CampaignView']);
    $app->post('CampaignEdit', ['uses' => 'User\CampaignController@CampaignEdit']);
    $app->post('CampaignUpdate', ['uses' => 'User\CampaignController@CampaignUpdate']);
    $app->post('CampaignDelete', ['uses' => 'User\CampaignController@CampaignDelete']);
    $app->post('CampaignList', ['uses' => 'User\CampaignController@CampaignList']);
    $app->post('AssignAddToCampaigns', ['uses' => 'User\CampaignController@AssignAddToCampaigns']);
    $app->post('CampaignListForAdserver', ['uses' => 'User\CampaignController@GetCampaignListForAdserver']);
    $app->post('CampaignStatistics', ['uses' => 'User\CampaignController@CampaignStatistics']);
    $app->post('AffiliateCampaignTypeList', ['uses' => 'User\CampaignController@AffiliateCampaignTypeList']);

    // User support ticket
    $app->post('GenerateTicket', ['uses' => 'User\TicketController@GenerateTicket']);
    $app->post('ShowAffiliateTickets', ['uses' => 'User\TicketController@ShowAffiliateTickets']);
    $app->post('ShowMessageByAffiliate', ['uses' => 'User\TicketController@ShowMessageByAffiliate']);
    $app->post('GetUnreadMessageByAffiliate', ['uses' => 'User\TicketController@GetUnreadMessageByAffiliate']);
    $app->post('GetUnreadMessageCountAffiliate', ['uses' => 'User\TicketController@GetUnreadMessageCountAffiliate']);
    $app->post('SendMessageByAffiliate', ['uses' => 'User\TicketController@SendMessageByAffiliate']);
    $app->post('UpdateTicketStatus', ['uses' => 'User\TicketController@UpdateTicketStatus']); 
    $app->post('AffiliateSupportCount', ['uses' => 'User\TicketController@AffiliateSupportCount']);

    /* Admin Add,Edit,Update,Delete,Active,Deactive News */
    $app->post('user/NewsList', ['uses' => 'User\NewsController@NewsList']);
    $app->post('user/LatestNews', ['uses' => 'User\NewsController@LatestNews']);
    $app->post('user/NewsView', ['uses' => 'User\NewsController@NewsView']);
    $app->post('user/NewsReadCount', ['uses' => 'User\NewsController@NewsReadCount']);
    $app->post('user/NewsReadCountDelete', ['uses' => 'User\NewsController@NewsReadCountDelete']);

    /* 
     * Common Master tables
     */
    $app->get('CountryList', ['uses' => 'Master\CountryController@CountryList']);
    $app->get('AdBrandList', ['uses' => 'Master\AdController@AdBrandList']);
    $app->get('AdSizeList', ['uses' => 'Master\AdController@AdSizeList']);
    $app->get('AdTypeList', ['uses' => 'Master\AdController@AdTypeList']);
    $app->get('CampaignTypeList', ['uses' => 'Master\CampaignController@CampaignTypeList']);
    $app->get('CommissionTypeList', ['uses' => 'Master\CommissionController@CommissionTypeList']);
    $app->get('LanguageList', ['uses' => 'Master\LanguageController@LanguageList']);
    $app->get('MenuList', ['uses' => 'Master\MenuController@MenuList']);
    $app->get('TicketTypeList', ['uses' => 'Master\TicketTypeController@TicketTypeList']);
    $app->get('RevenueTypeList', ['uses' => 'Master\RevenueTypeController@RevenueTypeList']);
    $app->get('RevenueTypeListForAssignAffiliate', ['uses' => 'Master\RevenueTypeController@RevenueTypeListForAssignAffiliate']);
    $app->get('RoleList', ['uses' => 'Master\RoleController@RoleList']);
    $app->get('CurrencyList', ['uses' => 'Master\CurrencyController@CurrencyList']);
    $app->get('PriorityList', ['uses' => 'Master\TicketTypeController@PriorityList']);
    $app->post('PaymentTypeList', ['uses' => 'Master\PaymentTypeController@PaymentTypeList']);
    $app->get('TicketStatus', ['uses' => 'Master\TicketTypeController@TicketStatus']);

    /*  Admin+Admin Users  */
    $app->post('auth/reset_password', ['uses' => 'Admin\AdminController@reset_password']);
    $app->post('Change_password/', ['uses' => 'Admin\AdminController@Change_password']);

    /* Admin login,logout,show subadmin */
    $app->post('auth/admin/login', ['uses' => 'Admin\AdminController@authenticate']);
    $app->post('IsValidAdminLoginToken', ['uses' => 'Admin\AdminController@IsValidAdminLoginToken']);
    $app->post('admin/ValidateToken', ['uses' => 'Admin\AdminController@ValidateToken']);
    $app->get('logged_user', ['uses' => 'Admin\AdminController@logged_user']);
    $app->post('logged_out', ['uses' => 'Admin\AdminController@logged_out']);
    $app->post('admin/Show_subadmin', ['uses' => 'Admin\AdminController@Show_subadmin']);

    /* Admin Add,Edit,Update menu */
    $app->post('admin/Add_menu', ['uses' => 'Admin\AdminController@Add_menu']);
    $app->get('admin/Edit_menu/{menu_id}', ['uses' => 'Admin\AdminController@Edit_menu']);
    $app->post('admin/Update_menu', ['uses' => 'Admin\AdminController@Update_menu']);

    /* Admin Add,Edit,Update Subadmin with permission */
    $app->post('admin/Add_subadmin', ['uses' => 'Admin\AdminController@Add_subadmin']);
    $app->post('admin/Edit_subadmin/', ['uses' => 'Admin\AdminController@Edit_subadmin']);
    $app->post('admin/Update_subadmin', ['uses' => 'Admin\AdminController@Update_subadmin']);
    $app->post('admin/Delete_subadmin/', ['uses' => 'Admin\AdminController@Delete_subadmin']);
    $app->post('ChangePassword/', ['uses' => 'Admin\AdminController@ChangePassword']);
    // Get Revenue Models By Revenue Type Id
    $app->post('admin/GetRevenueModelsByRevenueTypeId', ['uses' => 'Admin\AdminController@GetRevenueModelsByRevenueTypeId']);
    // web code module 
    $app->post('GetUrlData', ['uses' => 'Admin\AdminController@GetUrlData']);
    $app->post('GetPlainAffilaiteData', ['uses' => 'Admin\AdminController@GetPlainAffilaiteData']);
    $app->post('Success', ['uses' => 'Admin\AdminController@Success']);
    $app->post('FakePostLeadGeneration', ['uses' => 'Admin\AdminController@FakePostLeadGeneration']);
    $app->get('CurrencyConvertCall', ['uses' => 'Admin\AdminController@CurrencyConvertCall']);
    $app->post('admin/GeneralStatisticsAdmin', ['uses' => 'Admin\AdminController@GeneralStatisticsAdmin']);
    $app->post('admin/AdStatisticsAdmin', ['uses' => 'Admin\AdminController@AdStatisticsAdmin']);
    $app->post('admin/AffiliateStatisticsAdmin', ['uses' => 'Admin\AdminController@AffiliateStatisticsAdmin']);
    $app->post('admin/RevenueModelStatisticsAdmin', ['uses' => 'Admin\AdminController@RevenueModelStatisticsAdmin']);
    // admin dashboard API's
    $app->post('admin/GeneralStatisticsAdminDashboard', ['uses' => 'Admin\AdminController@GeneralStatisticsAdminDashboard']);
    $app->post('admin/ViewDetailBonusByAdminDashboard', ['uses' => 'Admin\AdminController@ViewDetailBonusByAdminDashboard']);
    $app->post('admin/AffiliateStatisticsAdminDashboard', ['uses' => 'Admin\AdminController@AffiliateStatisticsAdminDashboard']);
    $app->post('admin/AdStatisticsAdminDashboard', ['uses' => 'Admin\AdminController@AdStatisticsAdminDashboard']);
    $app->post('admin/RevenueModelStatisticsAdminDashboard', ['uses' => 'Admin\AdminController@RevenueModelStatisticsAdminDashboard']);

    $app->post('admin/GetAllLeadList', ['uses' => 'Admin\AdminController@GetAllLeadList']);
    $app->post('admin/GetAllCurrencyRateList', ['uses' => 'Admin\AdminController@GetAllCurrencyRateList']);
    $app->post('admin/CurrencyRateUpdate', ['uses' => 'Admin\AdminController@CurrencyRateUpdate']);


    /* 
        Utility / Cron job
    */
    // Bonus
    $app->get('admin/AffiliateAutoBonusGenerate', ['uses' => 'Admin\RevenueController@AffiliateAutoBonusGenerate']);
    // Currency
    $app->post('admin/CurrencyRateaAutoUpdate', ['uses' => 'Admin\AdminController@CurrencyRateaAutoUpdate']);
    // General Lead Information
    $app->post('admin/GeneralLeadInformationAutoUpload', ['uses' => 'Admin\AdminController@GeneralLeadInformationAutoUpload']);
    $app->get('admin/GeneralLeadInformationAutoRevenueGenereate', ['uses' => 'Admin\AdminController@GeneralLeadInformationAutoRevenueGenereate']);
    // Daily Lead Activity
    $app->post('admin/DailyLeadActivityAutoUpload', ['uses' => 'Admin\AdminController@DailyLeadActivityAutoUpload']);
    $app->get('admin/DailyLeadActivityAutoRevenueGenereate', ['uses' => 'Admin\AdminController@DailyLeadActivityAutoRevenueGenereate']);
    /*
        End. Utility / Cron job
    */

    // Decrypt password
    $app->post('decrypt', ['uses' => 'Admin\AdminController@decrypt']);
    $app->post('LeadActivityDate', ['uses' => 'Admin\AdminController@LeadActivityDate']);

    // Campaign details
    $app->post('admin/GetCampaignDetailsByCampaignId', ['uses' => 'Admin\AdminController@GetCampaignDetailsByCampaignId']);

    /* Admin Add,Edit,Update,Delete,Active,Deactive Advertisment */
    $app->post('admin/AddUpdateAd', ['uses' => 'Admin\AdController@AddUpdateAd']);
    $app->post('admin/AdsList', ['uses' => 'Admin\AdController@AdsList']);
    $app->post('admin/AdView', ['uses' => 'Admin\AdController@AdView']);
    $app->post('admin/AdEdit', ['uses' => 'Admin\AdController@AdEdit']);
    $app->post('admin/AdEnableDisable', ['uses' => 'Admin\AdController@AdEnableDisable']);

    // Admin add ad Brand, ad Type, ad Size
    $app->post('admin/AddNewAdBrand', ['uses' => 'Admin\AdController@AddNewAdBrand']);
    $app->post('admin/AddNewAdType', ['uses' => 'Admin\AdController@AddNewAdType']);
    $app->post('admin/AddNewAdSize', ['uses' => 'Admin\AdController@AddNewAdSize']);
    $app->get('admin/Show_affilite', ['uses' => 'Admin\AdController@Show_affilite']);


    /* Admin Add,Edit,Update,Delete,Active,Deactive News */
    $app->post('admin/AddUpdateNews', ['uses' => 'Admin\NewsController@AddUpdateNews']);
    $app->post('admin/NewsList', ['uses' => 'Admin\NewsController@NewsList']);
    $app->post('admin/NewsView', ['uses' => 'Admin\NewsController@NewsView']);
    $app->post('admin/NewsEdit', ['uses' => 'Admin\NewsController@NewsEdit']);
    $app->post('admin/AffiliateListByBrand', ['uses' => 'Admin\NewsController@AffiliateListByBrand']);
    $app->post('admin/NewsDisable', ['uses' => 'Admin\NewsController@NewsDisable']);

    // $app->post('auth/subadmin/login', ['uses' => 'SubAdmin\SubAdminController@subauthenticate']);
    $app->get('subadmin/Userpermission', ['uses' => 'SubAdmin\SubAdminController@Userpermission']);
    $app->post('subadmin/GetSubadminPermission', ['uses' => 'SubAdmin\SubAdminController@GetSubadminPermission']);

    // Admin Affiliate CRUD
    $app->post('admin/AdminAddUpdateAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminAddUpdateAffiliate']);
    $app->post('admin/AdminAddAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminAddAffiliate']);
    $app->post('admin/AdminUpdateAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminUpdateAffiliate']);
    $app->post('admin/AdminListAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminListAffiliate']);
    $app->post('admin/ActiveAffiliateList', ['uses' => 'Admin\AdminAffiliateController@ActiveAffiliateList']);
    $app->post('admin/AdminGetAffiliateDetailsByUserId', ['uses' => 'Admin\AdminAffiliateController@AdminGetAffiliateDetailsByUserId']);
    $app->post('admin/AdminActivateAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminActivateAffiliate']);
    $app->post('admin/AdminDeleteAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminDeleteAffiliate']);
    $app->post('admin/AdminEnableDisableAffiliate', ['uses' => 'Admin\AdminAffiliateController@AdminEnableDisableAffiliate']);
    $app->post('admin/IntegrationAffiliateList', ['uses' => 'Admin\AdminAffiliateController@IntegrationAffiliateList']);
    $app->post('admin/SubAffiliateList', ['uses' => 'Admin\AdminAffiliateController@SubAffiliateList']);

    // Admin Revenue CRUD
    $app->post('admin/AddRevenueOptionModel', ['uses' => 'Admin\RevenueController@AddRevenueOptionModel']);
    $app->post('admin/ViewRevenueOptionModel', ['uses' => 'Admin\RevenueController@ViewRevenueOptionModel']);
    $app->post('admin/ViewRevenueOptionModelV2', ['uses' => 'Admin\RevenueController@ViewRevenueOptionModelV2']);
    $app->post('admin/EditRevenueOptionModel', ['uses' => 'Admin\RevenueController@EditRevenueOptionModel']);
    $app->post('admin/UpdateRevenueOptionModel', ['uses' => 'Admin\RevenueController@UpdateRevenueOptionModel']);
    $app->post('admin/ListRevenueOptionModel', ['uses' => 'Admin\RevenueController@ListRevenueOptionModel']);
    $app->post('admin/AddRevenueBonusUsers', ['uses' => 'Admin\RevenueController@AddRevenueBonusUsers']);

    $app->post('admin/AffiliateManualBonusAssign', ['uses' => 'Admin\RevenueController@AffiliateManualBonusAssign']);
    // Get Affilaite List For Revenue Model 
    $app->post('admin/GetAffiliateListForRevenueModel', ['uses' => 'Admin\RevenueController@GetAffiliateListForRevenueModel']);
    $app->post('admin/AssignRevenueModelToAffiliates', ['uses' => 'Admin\RevenueController@AssignRevenueModelToAffiliates']);
    $app->post('admin/DeleteRevenueModel', ['uses' => 'Admin\RevenueController@DeleteRevenueModel']);

    $app->post('admin/GetAffiliateBalanceList', ['uses' => 'Admin\PaymentController@GetAffiliateBalanceList']);
    $app->post('admin/AdminAffiliateRevenueDetails', ['uses' => 'Admin\PaymentController@AdminAffiliateRevenueDetails']);
    $app->post('admin/AdminAffiliateRevenueList', ['uses' => 'Admin\PaymentController@AdminAffiliateRevenueList']);
    $app->post('admin/AdminAffiliateRevenueAcceptReject', ['uses' => 'Admin\PaymentController@AdminAffiliateRevenueAcceptReject']);

    // Affiliate Payment API
    $app->post('AddAffiliatePaymentRequest', ['uses' => 'User\PaymentController@AddAffiliatePaymentRequest']);
    $app->post('ShowAffiliatePaymentRequest', ['uses' => 'User\PaymentController@ShowAffiliatePaymentRequest']);
    $app->post('GetAffiliateBalanceDetail', ['uses' => 'User\PaymentController@GetAffiliateBalanceDetail']);
    // affiliate dashboard
    $app->post('ShowAffiliateRevenue', ['uses' => 'User\PaymentController@ShowAffiliateRevenue']);
    $app->post('admin/GetAdminAffiliatePaymentRequest', ['uses' => 'Admin\PaymentController@GetAdminAffiliatePaymentRequest']);
    $app->post('admin/GetAdminAffiliatePaymentDeclined', ['uses' => 'Admin\PaymentController@GetAdminAffiliatePaymentDeclined']);

    $app->post('admin/GetSingleAffiliatePaymentRequest', ['uses' => 'Admin\PaymentController@GetSingleAffiliatePaymentRequest']);
    $app->post('admin/RejectPaymentRequest', ['uses' => 'Admin\PaymentController@RejectPaymentRequest']);
    $app->post('admin/GetAdminAffiliatePaymentHistory', ['uses' => 'Admin\PaymentController@GetAdminAffiliatePaymentHistory']);
    $app->post('admin/GetPaymentDetailsByUserPaymentId', ['uses' => 'Admin\PaymentController@GetPaymentDetailsByUserPaymentId']);
    $app->post('admin/CreatePayment', ['uses' => 'Admin\PaymentController@CreatePayment']);
    $app->post('GetAdDetailsByAdId', ['uses' => 'Admin\AdController@GetAdDetailsByAdId']);
    $app->post('admin/IntegrationLeadList', ['uses' => 'Admin\LeadController@IntegrationLeadList']);
    $app->post('admin/GenrateLead', ['uses' => 'Admin\LeadController@GenrateLead']);
    $app->post('admin/ExportAdminPaymentDetails', ['uses' => 'Admin\PaymentController@ExportAdminPaymentDetails']);
    $app->post('admin/UploadLeadActivitySheet', ['uses' => 'Admin\ExcelController@UploadLeadActivitySheet']);
    $app->post('admin/GetLeadInformationFiles', ['uses' => 'Admin\ExcelController@GetLeadInformationFiles']);
    $app->post('admin/GetLeadActivityFiles', ['uses' => 'Admin\ExcelController@GetLeadActivityFiles']);
    $app->post('admin/GetCurrencyRateFiles', ['uses' => 'Admin\ExcelController@GetCurrencyRateFiles']);
    $app->post('admin/GetImportFiles', ['uses' => 'Admin\ExcelController@GetImportFiles']);
    $app->post('admin/CallProcessLeadActivity', ['uses' => 'Admin\ExcelController@CallProcessLeadActivity']);
    $app->post('admin/CallProcessLeadInfo', ['uses' => 'Admin\ExcelController@CallProcessLeadInfo']);

    // admin support ticket managemant    
    $app->post('admin/AdminShowAllTickets', ['uses' => 'Admin\TicketController@AdminShowAllTickets']);
    $app->post('admin/AdminShowTicketMessages', ['uses' => 'Admin\TicketController@AdminShowTicketMessages']);
    $app->post('admin/AdminShowTicketMessages', ['uses' => 'Admin\TicketController@AdminShowTicketMessages']);
    $app->post('SendMessageByAdmin', ['uses' => 'Admin\TicketController@SendMessageByAdmin']);
    $app->post('admin/AdminSupportCount', ['uses' => 'Admin\TicketController@AdminSupportCount']);

    $app->post('admin/GetAffiliateBalanceFromUserId', ['uses' => 'Admin\PaymentController@GetAffiliateBalanceFromUserId']);
    $app->post('GetRevenueFromSubAffiliate', ['uses' => 'Admin\ExcelController@GetRevenueFromSubAffiliate']);
});
