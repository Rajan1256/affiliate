<?php

namespace App\Http\Controllers\User;

use Validator;
use App\UserToken;
use App\News;
use App\NewsCount;
use App\UserAdBrand;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class NewsController extends BaseController
{
    private $request;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->storage_path = getenv('STORAGE_URL');
    }

    public function NewsList(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('token'));
            if ($UserId) {
                $TimeZoneOffSet = $request->TimeZoneOffSet;
                if ($TimeZoneOffSet == '')
                    $TimeZoneOffSet = 0;

                $AdBrandIds = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');

                $news = News::where('IsPublic', 1)->WhereHas('NewsBrandList', function ($qr1) use ($AdBrandIds) {
                    $qr1->whereIn('AdBrandId', $AdBrandIds);
                })->where('IsActive', 1)->orWhereHas('NewsList', function ($qr2) use ($UserId) {
                    $qr2->where('UserId', $UserId);
                })->where('IsActive', 1)->orderBy('UpdatedAt', 'desc')->get();

                $newsSelected = [];
                foreach ($news as $value) {
                    if ($value['NewsImage'] != '')
                        $image = $this->storage_path . 'app/news/' . $value['NewsImage'];
                    else
                        $image = '';
                    // if (strlen($value['Description']) > 1000) {
                    //     $Description = substr($value['Description'], 0, 998) . "..";
                    // } else {
                    //     $Description = $value['Description'];
                    // }

                    $var = [
                        'NewsId' => $value['NewsId'],
                        'Title' => $value['Title'],
                        'NewsImage' => $image,
                        'Description' => $value['Description'],
                        'IsPublic' => $value['IsPublic'] == 0 ? 'Private' : 'Public',
                        'CreatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                        'UpdatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['UpdatedAT']))),
                    ];
                    array_push($newsSelected, $var);
                }
                $res = [
                    'IsSuccess' => true,
                    'Message' => 'List all news.',
                    'TotalCount' => $news->count(),
                    'Data' => array('NewsList' => $newsSelected)
                ];
                return response()->json($res, 200);
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
    }

    public function LatestNews(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('token'));
            if ($UserId) {
                $TimeZoneOffSet = $request->TimeZoneOffSet;
                if ($TimeZoneOffSet == '')
                    $TimeZoneOffSet = 0;

                $AdBrandIds = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');

                $news = News::where('IsPublic', 1)->WhereHas('NewsBrandList', function ($qr1) use ($AdBrandIds) {
                    $qr1->whereIn('AdBrandId', $AdBrandIds);
                })->where('IsActive', 1)->orWhereHas('NewsList', function ($qr2) use ($UserId) {
                    $qr2->where('UserId', $UserId);
                })->where('IsActive', 1)->orderBy('UpdatedAt', 'desc')->take(3)->get();

                $newsSelected = [];
                foreach ($news as $value) {
                    if ($value['NewsImage'] != '')
                        $image = $this->storage_path . 'app/news/' . $value['NewsImage'];
                    else
                        $image = '';
                    $var = [
                        'NewsId' => $value['NewsId'],
                        'Title' => $value['Title'],
                        'NewsImage' => $image,
                        'Description' => $value['Description'],
                        'IsPublic' => $value['IsPublic'] == 0 ? 'Private' : 'Public',
                        'CreatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                        'UpdatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['UpdatedAT']))),
                    ];
                    array_push($newsSelected, $var);
                }
                $res = [
                    'IsSuccess' => true,
                    'Message' => 'List all news.',
                    'TotalCount' => $news->count(),
                    'Data' => array('NewsList' => $newsSelected)
                ];
                return response()->json($res, 200);
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
    }

    public function NewsView(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('token'));
            if ($UserId) {
                $validator = Validator::make($request->all(), [
                    'NewsId' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                $TimeZoneOffSet = $request->TimeZoneOffSet;
                if ($TimeZoneOffSet == '')
                    $TimeZoneOffSet = 0;

                $newsData = News::find($request->NewsId);
                if ($newsData) {
                    $IsPublic = ($newsData['IsPublic'] == 0) ? 'Private' : 'Public';
                    $NewsDetails = [
                        'NewsId' => $newsData['NewsId'],
                        'Title' => $newsData['Title'],
                        'NewsImage' => $this->storage_path . 'app/news/' . $newsData['BannerImage'],
                        'IsPublic' => $IsPublic,
                        'CreatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($newsData['CreatedAt']))),
                        'UpdatedAT' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($newsData['UpdatedAT']))),
                    ];
                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'View news.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $NewsDetails)
                    ], 200);
                } else {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'News not found.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => [])
                    ], 200);
                }
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res, 200);
    }

    public function NewsReadCount(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if ($UserId) {
                $total_count = NewsCount::where('UserId', $UserId)->count();
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Affiliate total news count.',
                    "TotalCount" =>  $total_count,
                    'Data' => ['NewsCount' => $total_count]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }


    public function NewsReadCountDelete(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {

                $total_count = NewsCount::where('UserId', $UserId)->delete();

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Affiliate total news count deleted successfully.',
                    'Data' => ['NewsCount' => 0]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }
}
