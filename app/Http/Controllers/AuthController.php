<?php
namespace App\Http\Controllers;
use Validator;
use DB; 
use Illuminate\Support\Facades\Mail;
use App\User;
use App\ResetPassword;
use App\UserVerification;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Routing\Controller as BaseController;
class AuthController extends BaseController
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    private $request;
    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request) {
        $this->request = $request;
    }
    /**
     * Create a new token.
     *
     * @param  \App\User   $user
     * @return string
     */
    protected function jwt(User $user) {
        $payload = [
            'iss' => "lumen-jwt", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => time(), // Time when JWT was issued.
            'exp' => time() + 60*60 // Expiration time
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('JWT_SECRET'));
    }
    /**
     * Authenticate a user and return the token if the provided credentials are correct.
     *
     * @param  \App\User   $user
     * @return mixed
     */
    public function authenticate(User $user) {
        $this->validate($this->request, [
            'email'     => 'required|email',
            'password'  => 'required',
            'user_type'=>'required',
        ]);
        // Find the user by email
        $user = User::where('email', $this->request->input('email'))->first();
        if (!$user) {
            // You wil probably have some sort of helpers or whatever
            // to make sure that you have the same response format for
            // differents kind of responses. But let's return the
            // below respose for now.
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Email does not exist.'
            ], 400);
        }
        // Verify the password and generate the token
        if (Hash::check($this->request->input('password'), $user->password)) {

            if($this->request->input('user_type')==1)
            {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Login successfully.',
                    'token' => $this->jwt($user)
                ], 200);
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'You are not Admin.'
                ], 400);
            }

        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Email or password is wrong.'
            ], 400);
        }
        // Bad Request response

    }

    public function registerAffiliate(Request $request) { 
        $validator = Validator::make($request->all(), [
            'FirstName'  => 'required',
            'LastName'  => 'required',
            'EmailId'     => 'required|email|unique:users',
            'ConfirmEmailId'     => 'required|email|same:EmailId',
            'Password'  => 'required',
            'ConfirmPassword'  => 'required|same:Password',
            'UserType'=>'required',
        ]);
        if ($validator->fails()) { 
            return response()->json([
                'IsSuccess' => false, 
                'Message' => 'Something went wrong.',
                "TotalCount" => count($validator->errors()),
                "Data" => array('Error' => $validator->errors())                
            ], 400);
        }

        $User = new User(); 
        $User->FirstName = $request->FirstName;
        $User->LastName = $request->LastName;
        $User->EmailId = $request->EmailId;
        $User->Password = Hash::make($request->Password);
        $User->UserType = $request->UserType;

        $User->Title = $request->Title;
        $User->Phone = $request->Phone;
        $User->Currency = $request->Currency;
        $User->Address = $request->Address;
        $User->City = $request->City;
        $User->State = $request->State;
        $User->Postal_code = $request->Postal_code;
        $User->Company_name = $request->Company_name;
        $User->save();

        $update = User::find($User->id);
        $User->remember_token = $this->jwt($update);
        $update->save();

        $name = $request->FirstName.' '.$request->LastName;
        $email = $request->EmailId; 
        $verification_code = str_random(30); //Generate verification code

        DB::table('user_verifications')->insert(['user_id'=>$User->id, 'token'=>$verification_code]);
        $subject = "Please verify your email address.";
        Mail::send('email.verify', ['name' => $name, 'verification_code' => $verification_code],
        function($mail) use ($email, $name, $subject){
            $mail->from(getenv('FROM_EMAIL_ADDRESS'), "Royal Company");
            $mail->to($email, $name);
            $mail->subject($subject);
        });
        return response()->json([
            'IsSuccess' => true, 
            'Message' => 'Thanks for signing up! Please check your email to complete your registration.',
            "TotalCount" => 0,
            "Data" => null
        ], 200); 
    }

    public function verifyUser($verification_code)
    {
        $check = DB::table('user_verifications')->where('token',$verification_code)->first();
        if(!is_null($check)){
            $user = User::find($check->user_id); 
            $verified = UserVerification::find($check->id);
            if($user->is_verified == 1){
                return response()->json([
                    'IsSuccess'=> false,
                    'Message'=> 'Account already verified.',
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);
            }
            if($verified->completed == 1){
                return response()->json([
                    'IsSuccess'=> false,
                    'Message'=> 'Verification code Expired.',
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);
            }
            $user->is_verified = 1;
            $user->save();
            $verified->completed = 1;
            $verified->save();
            return response()->json([
                'IsSuccess'=> true,
                'Message'=> 'You have successfully verified your email address.',
                "TotalCount" => 0,
                "Data" => null
            ], 200);
        }
        return response()->json([
            'IsSuccess'=> false,
            'Message'=> "Verification code is invalid.",
            "TotalCount" => 0,
            "Data" => null
        ], 400);
    }

    public function loginAffiliate(Request $request) 
    {
        $this->validate($this->request, [
            'email'     => 'required|email',
            'password'  => 'required',
            'UserType'=>'required',
        ]);
        // Find the user by email
        $user = User::where('EmailId', $request->email)->first();
        if (!$user) { 
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Email does not exist.',
                "TotalCount" => 0,
                "Data" => null
            ], 400);
        }
        // Verify the password and generate the token
        if (Hash::check($request->password, $user->Password)) {
            if($user->is_verified == 0){
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'You Account is not activated.',
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);                
            }
            else if($user->admin_verified == 0){
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Your Account is not activated from admin side.',
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);                
            }
            else if($request->UserType==3)
            {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Login successfully.',
                    "TotalCount" => 0,
                    "Data" =>  array('token' => $this->jwt($user), 'User' => $user)
                ], 200);
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'You are not Affiliate.',
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);
            }

        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Email or password is wrong.'
            ], 400);
        }
    }


    public function AffiliateResetPassword(Request $request)
    { 
        $validator = Validator::make($request->all(), [
            'EmailId'     => 'required|email',
        ]);
        if ($validator->fails()) { 
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Something went wrong.',
                "TotalCount" => count($validator->errors()),
                "Data" => array('Error' => $validator->errors())
            ], 400);
        }

        // Find the user by email
        $user = User::where('EmailId', $this->request->input('EmailId'))->first();
        $rand_str = str_random(16);
        if($user)
        {
            $pass = ResetPassword::where('UserId',$user->id)->count();
            if($pass==1)
            {
                ResetPassword::where('UserId',$user->id)->update([
                    'PasswordResetToken'=>$rand_str,
                    'EmailId'=>$user->EmailId,
                    'updated_at'=>date('Y-m-d H:i:s'),
                ]);

                $link_url = env('APP_URL').'api/AffiliateChangePassword/'.$rand_str;
                $data = array('name'=>$user->FirstName,'link'=>$link_url);
                $userEmail = $user->EmailId;
                $userName = $user->FirstName;
                Mail::send('email.AffiliateResetPassword', $data, function($message) use ($userEmail, $userName) {
                    $message->to($userEmail, $userName)->subject('Reset Password');
                    $message->from(getenv('FROM_EMAIL_ADDRESS'), "Royal Company");
                });

                return response()->json([
                    'IsSuccess'=> true,
                    'Message'=> "Reset Password link send on your EmailId",
                    "TotalCount" => 0,
                    "Data" => null
                ], 400);
            }
            else
            {
                ResetPassword::create([
                    'UserId'=> $user->id,
                    'PasswordResetToken'=>$rand_str,
                    'EmailId'=>$user->EmailId,
                    'created_at'=>date('Y-m-d H:i:s'),
                    'updated_at'=>date('Y-m-d H:i:s'),
                ]);

                $link_url = env('APP_URL').'api/AffiliateChangePassword/'.$rand_str;
                $data = array('name'=>$user->FirstName,'link'=>$link_url);
                $userEmail = $user->EmailId;
                $userName = $user->FirstName;
                Mail::send('email.AffiliateResetPassword', $data, function($message) use ($userEmail, $userName) {
                    $message->to($userEmail, $userName)->subject('Reset Password');
                    $message->from('xyz@gmail.com', 'Affiliate System');
                }); 

                return response()->json([ 
                    'IsSuccess'=> true,
                    'Message'=> "Reset Password link send on your EmailId",
                    "TotalCount" => 0,
                    "Data" => null
                ], 200);
            }
        }
        else
        {
            return response()->json([ 
                'IsSuccess'=> false,
                'Message'=> "email is not available.",
                "TotalCount" => 0,
                "Data" => null
            ], 400);

        }
    }

    public function affiliateChangePassword($token,Request $request)
    {
        $rst_pass = ResetPassword::where('PasswordResetToken',$token)->first();

        if($rst_pass)
        {
            $validator = Validator::make($request->all(), [
                'Password'  => 'required',
                'ConfirmPassword'=>'required|same:Password'
            ]);
            if ($validator->fails()) { 
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Something went wrong.',
                    "TotalCount" => count($validator->errors()),
                    "Data" => array('Error' => $validator->errors())
                ], 400);
            }

            User::where('id',$rst_pass->UserId)->update([
                'Password'=>password_hash($request->Password, PASSWORD_BCRYPT),
            ]);

            ResetPassword::where('UserId',$rst_pass->UserId)->delete();
            return response()->json([
                'IsSuccess'=> true,
                'Message'=> "Password Update successfully.",
                "TotalCount" => 0,
                "Data" => null
            ], 200);
        }
        else
        {
            return response()->json([
                'IsSuccess'=> false,
                'Message'=> "Invalid Token.",
                "TotalCount" => 0,
                "Data" => null
            ], 400);            
        }
    }

    public function LogoutUser(Request $request){
        $token = $this->jwt->getToken();
    
        if($this->jwt->invalidate($token)){
            return response()->json([
                'message' => 'User logged off successfully!'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to logout user. Try again.'
            ], 500);
        }
    }

}