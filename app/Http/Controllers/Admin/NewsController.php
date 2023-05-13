<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\User;
use App\News;
use App\NewsList;
use App\NewsCount;
use App\NewsBrandList;
use App\AdBrandMaster;
use App\UserAdBrand;
use App\UserToken;
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

  public function AddUpdateNews(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $InputData = json_decode($request->NewsInputData);
          // print_r($InputData); die;
          if ($request->hasFile('NewsImage')) {
            $image = $request->file('NewsImage');
            $name = 'News-' . $log_user->UserId . '' . time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = storage_path('app/news');
            $image->move($destinationPath, $name);
            // $full_path = env('STORAGE_URL') . 'storage/app/adds/'.$name;
          } else {
            $name = "";
          }
          if ($InputData->IsPublic)
            $IsPublic = 1;
          else
            $IsPublic = 0;

          if (isset($InputData->NewsId) && $InputData->NewsId != '') {
            $NewsData = News::find($InputData->NewsId);
            // $image_path = storage_path("app/news/{$NewsData->NewsImage}");
            // File::delete("storage/app/news/".$NewsData->NewsImage);
            if ($request->hasFile('NewsImage') && $NewsData->NewsImage != '') {
              $image_data = storage_path("app/news/{$NewsData->NewsImage}");
              if (File::exists($image_data)) {
                unlink($image_data);
              }
            }

            $NewsData->Title = $InputData->Title;
            if ($request->hasFile('NewsImage')) {
              $NewsData->NewsImage = $name;
            }
            $NewsData->Title = $InputData->Title;
            $NewsData->Description = $InputData->Description;
            $NewsData->IsPublic  = $InputData->IsPublic;
            $NewsData->UpdatedBy = $log_user->UserId;
            $NewsData->save();
            // NewsList::where('NewsId', $NewsData->NewsId)->delete();
            // NewsBrandList::where('NewsId', $NewsData->NewsId)->delete();
            $id = $NewsData->NewsId;

            $Message = 'News updated successfully.';
          } else {
            $create = News::create([
              'Title' => $InputData->Title,
              'NewsImage' => $name,
              'Description' => $InputData->Description,
              'IsPublic' => $IsPublic,
              'CreatedBy' => $log_user->UserId
            ]);
            $id = $create->NewsId;

            $Message = 'News added successfully.';
          }
          // Private news assign
          NewsCount::where('NewsId', $id)->delete();
          NewsList::where('NewsId', $id)->delete();
          if ($InputData->IsPublic == 0) {
            $AdAffiListOld = NewsList::where('NewsId', $id)->pluck('UserId'); // old list
            $oldArray = [];
            foreach ($AdAffiListOld as $oldId) {
              array_push($oldArray, $oldId);
            }
            $new_array = $InputData->AffiliateUserIds; // New list

            foreach ($AdAffiListOld as $OldData) {
              if (!in_array($OldData, $new_array)) {
                NewsList::where('NewsId', $id)->where('UserId', $OldData)->delete();  // delete row if old is not found
              }
            }

            foreach ($new_array as $UserId) {
              if (!in_array($UserId, $oldArray)) {
                NewsList::create([
                  'NewsId' => $id,
                  'UserId' => $UserId,
                  'CreatedBy' => $log_user->UserId
                ]);  // add new row if new found

                NewsCount::updateOrCreate(
                  [
                    'NewsId' => $id,
                    'UserId' => $UserId
                  ],
                  [
                    'NewsId' => $id,
                    'UserId' => $UserId
                  ]
                );
              }
            }
            /* End. update assigned news brand of news */
          } else {
            // if change private to public news deleted all assined user

            /* update assigned news brand of news */
            $AdBrandListOld = NewsBrandList::where('NewsId', $id)->pluck('AdBrandId'); // old list
            $oldArray = [];
            foreach ($AdBrandListOld as $oldId) {
              array_push($oldArray, $oldId);
            }
            $AdBrnadListNew = $InputData->AdBrandIds; // New list

            foreach ($AdBrandListOld as $OldData) {
              if (!in_array($OldData, $AdBrnadListNew)) {
                NewsBrandList::where('NewsId', $id)->where('AdBrandId', $OldData)->delete();  // delete row if old is not found
              }
            }
            foreach ($AdBrnadListNew as $NewId) {
              if (!in_array($NewId, $oldArray)) {
                NewsBrandList::create([
                  'NewsId' => $id,
                  'AdBrandId' => $NewId,
                  'CreatedBy' => $log_user->UserId
                ]);  // add new row if new found
              }
            }
            $PublicUser = NewsBrandList::with('UserAdBrand')
              ->where('NewsId', $id)->get();

            foreach ($PublicUser as $rw) {
              foreach ($rw['UserAdBrand'] as $rs) {
                NewsCount::updateOrCreate(
                  [
                    'NewsId' => $rw->NewsId,
                    'UserId' => $rs['UserId']
                  ],
                  [
                    'NewsId' => $rw->NewsId,
                    'UserId' => $rs['UserId']
                  ]
                );
              }
            }
          }

          /* End. update assigned news brand of news */

          $res = [
            'IsSuccess' => true,
            'Message' => $Message,
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res);
        }
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
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

  public function NewsList(Request $request)
  {
    try {
      $check = new UserToken();

      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $news = News::orderBy('NewsId', 'desc')->where('IsActive', 1)->get();
          $newsSelected = [];
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;
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
              'IsActive' => $value['IsActive'],
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
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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
      return response()->json($res, 200);
    }
  }

  public function NewsView(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
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

        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $newsData = News::find($request->NewsId);
          if ($newsData) {
            $IsPublic = ($newsData['IsPublic'] == 0) ? 'Private' : 'Public';
            // $user = AdAffiliateList::with('UserSingle')->where('AdId', '=', $request->AdId)->get();
            $NewsId = $request->NewsId;
            $user = User::with('NewsAffiliate')->whereHas('NewsAffiliate', function ($qr) use ($NewsId) {
              $qr->where('NewsId', '=', $NewsId);
            })->where('RoleId', 3)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('IsDeleted', 1)->get();
            $userList = [];
            foreach ($user as $value) {
              $arr = [
                'UserId' => $value['UserId'],
                'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
                'EmailId' => $value['EmailId']
              ];
              array_push($userList, $arr);
            }
            $NewsBrandList = NewsBrandList::where('NewsId', $NewsId)->pluck('AdBrandId');
            $Brand = AdBrandMaster::whereIn('AdBrandId', $NewsBrandList)->get();
            $brandList = [];
            foreach ($Brand as $BrandData) {
              $arr2 = [
                'Title' => $BrandData['Title'],
              ];
              array_push($brandList, $arr2);
            }

            $NewsDetails = [
              'NewsId' => $newsData['NewsId'],
              'Title' => $newsData['Title'],
              'NewsImage' => env('STORAGE_URL') . 'app/news/' . $newsData['NewsImage'],
              'IsPublic' => $IsPublic,
              'CreatedAT' => (string) $newsData['CreatedAt'],
              'UpdatedAT' => (string) $newsData['UpdatedAt'],
              'AssignAffiliateList' => $userList,
              'BrandList' => $brandList
            ];
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'View news with assign affiliates.',
              'TotalCount' => 0,
              'Data' => array('NewsDetails' => $NewsDetails)
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'News not found.',
              'TotalCount' => 0,
              'Data' => array('NewsDetails' => [])
            ], 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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

  public function NewsEdit(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {

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

        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $newsData = News::find($request->NewsId);
          if ($newsData) {
            $IsPublic = ($newsData['IsPublic'] == 0) ? 'Private' : 'Public';
            // $user = AdAffiliateList::with('UserSingle')->where('AdId', '=', $request->AdId)->get();
            $NewsId = $request->NewsId;
            $user = User::with(['NewsAffiliate' => function ($qr) use ($NewsId) {
              $qr->where('NewsId', $NewsId);
            }])->where('RoleId', 3)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('IsDeleted', 1)->get();
            $userList = [];
            foreach ($user as $value) {
              $IsSelected = 0;
              if ($value['NewsAffiliate']) {
                $IsSelected = 1;
              }
              $arr = [
                'UserId' => $value['UserId'],
                'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
                'EmailId' => $value['EmailId'],
                'IsSelected' => $IsSelected
              ];
              array_push($userList, $arr);
            }
            usort($userList, function ($a, $b) {
              return $b['IsSelected'] - $a['IsSelected'];
            });

            $NewsBrandList = AdBrandMaster::with(['NewsBrandList' => function ($qr) use ($NewsId) {
              $qr->where('NewsId', $NewsId);
            }])->get();
            // $Brand = AdBrandMaster::whereIn('AdBrandId', $NewsBrandList)->get(); 
            $brandList = [];
            foreach ($NewsBrandList as $BrandData) {
              $IsSelected = 0;
              if ($BrandData['NewsBrandList']) {
                $IsSelected = 1;
              }
              $arr2 = [
                'AdBrandId' => $BrandData['AdBrandId'],
                'Title' => $BrandData['Title'],
                'IsSelected' => $IsSelected,
              ];
              array_push($brandList, $arr2);
            }
            usort($brandList, function ($ab, $bb) {
              return $bb['IsSelected'] - $ab['IsSelected'];
            });

            $NewsImage = '';
            if ($newsData['NewsImage'] != '')
              $NewsImage = env('STORAGE_URL') . 'app/news/' . $newsData['NewsImage'];
            $NewsDetails = [
              'NewsId' => $newsData['NewsId'],
              'Title' => $newsData['Title'],
              'NewsImage' => $NewsImage,
              'Description' => $newsData['Description'],
              'IsPublic' => $newsData['IsPublic'],
              'CreatedAT' => (string) $newsData['CreatedAt'],
              'UpdatedAT' => (string) $newsData['UpdatedAt'],
              'AssignAffiliateList' => $userList,
              'BrandList' => $brandList,
            ];
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'View news with assign affiliates.',
              'TotalCount' => 0,
              'Data' => array('NewsDetails' => $NewsDetails)
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'News not found.',
              'TotalCount' => 0,
              'Data' => array('NewsDetails' => [])
            ], 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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
      return response()->json($res, 200);
    }
  }

  public function AffiliateListByBrand(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'BrandIds' => 'required',
          'NewsId' => 'nullable',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $NewsId = $request->NewsId;
          $UsersList = UserAdBrand::whereIn('AdBrandId', $request->BrandIds)->groupBy('UserId')->pluck('UserId');

          $user = User::whereIn('UserId', $UsersList)->where('RoleId', 3)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('IsDeleted', 1)->get();

          $user = User::with(['NewsAffiliate' => function ($qr) use ($NewsId) {
            $qr->where('NewsId', $NewsId);
          }])->whereIn('UserId', $UsersList)->where('RoleId', 3)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('IsDeleted', 1)->get();

          $userList = [];
          foreach ($user as $value) {
            $IsSelected = 0;
            if ($value['NewsAffiliate']) {
              $IsSelected = 1;
            }
            $arr = [
              'UserId' => $value['UserId'],
              'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
              'EmailId' => $value['EmailId'],
              'IsSelected' => $IsSelected
            ];
            array_push($userList, $arr);
          }
          usort($userList, function ($a, $b) {
            return $b['IsSelected'] - $a['IsSelected'];
          });

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Affiliate list geting successfully.',
            'TotalCount' => count($userList),
            'Data' => array('userList' => $userList)
          ], 200);
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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
      return response()->json($res, 200);
    }
  }

  public function NewsDisable(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

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
          $newsData = News::find($request->NewsId);
          if ($newsData) {
            News::where('NewsId', $request->NewsId)->update([
              'IsActive' => 0
            ]);
            NewsCount::where('NewsId', $request->NewsId)->delete();
            $res = [
              'IsSuccess' => true,
              'Message' => "News deleted successfully",
              'TotalCount' => 0,
              'Data' => null
            ];
            return response()->json($res, 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'News not found.',
              'TotalCount' => 0,
              'Data' => []
            ], 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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
    } catch (Exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res, 200);
    }
  }

  /*
  public function NewsEnableDisable(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

          $validator = Validator::make($request->all(), [
            'NewsId' => 'required',
            'Status' => 'required',
          ]);

          if ($validator->fails()) {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Something went wrong.',
              "TotalCount" => count($validator->errors()),
              "Data" => array('Error' => $validator->errors())
            ], 200);
          }
          News::where('NewsId', $request->NewsId)->update([
            'IsActive' => $request->Status
          ]);
          if ($request->Status == 1) {
            $ActiveMessage = "News enable successfully.";
          } else {
            $ActiveMessage = "News disable successfully.";
          }
          $res = [
            'IsSuccess' => true,
            'Message' => $ActiveMessage,
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
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
    } catch (Exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res, 200);
    }
  } */
}
