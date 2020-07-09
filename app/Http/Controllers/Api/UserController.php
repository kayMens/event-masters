<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Vendor;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class UserController extends Controller
{
    public $successStatus = 200;
    
    /** 
     * Login api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function login(){ 
        
        if(Auth::attempt([
            'phone' => request('phone'), 
            'password' => request('password')])){
            
            $auth = Auth::user(); 
            if(! $auth->active) {
                $user = User::find($auth->id);
                $otp = rand(100000, 999999);
                $user->otp = $otp;
                $user->save();
                //send sms
                return response()->json(['error' => 'User not active'], $this->successStatus);
            }
            if($auth->isVendor()){
                $auth['vendor_complete'] = (bool) Vendor::where('user_id', $auth->id)->first()->complete;
            }else{
                $auth['vendor_complete'] = false;
            }
            $auth['token'] =  $auth->createToken("EventMaster-User-$auth->id")->accessToken; 
            unset($auth['otp']);
            unset($auth['created_at']);
            unset($auth['updated_at']);
            unset($auth['phone_verified_at']);
            return response()->json(['user' => $auth], $this->successStatus); 
        } 
        
        return response()->json(['error'=>'Unauthorised'], 400); 
    }

    /** 
     * Register api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function register() 
    { 
        $validator = Validator::make(request()->all(), [ 
            'name' => 'required', 
            'phone' => 'required', 
            'password' => 'required',
            'type' => 'required',
            ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }
        
        $user = User::where(['phone' => request('phone')])->first(); 
        if(! empty($user)){
            return response()->json(['error' => 'User already exist'], 401);            
        }

        $input = request()->all(); 
        $input['password'] = bcrypt($input['password']);
        $otp = rand(100000, 999999); 
        $input['otp'] = $otp;
        $input['active'] = 0;
        $user = User::create($input); 
        if($input['type'] == 'vendor')
        {
            Vendor::create([
                'name' => 'Not Setup',
                'category' => 'Not Setup',
                'user_id' => $user->id,
                'dir_path' => "V". $user->id ."_". time()
            ]);
        }
        //send sms
        $this->sendSms($user->phone, $otp);
        return response()->json(['message' => 'User created'], $this->successStatus); 
    }

    /**
     * 
     * Activate account api
     * 
     * @return \Illuminate\Http\Response
     */
    public function activate() {
        
        $validator = Validator::make(request()->all(), [ 
            'phone' => 'required', 
            'otp' => 'required|integer'
        ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }
        
        $user = User::where(['phone' => request('phone'), 'otp' => request('otp')])->first();
        if($user == null){
            return response()->json(['error' => 'User not existing'], 400);
        }
        $user->active = 1;
        $user->otp = NULL;
        $user->phone_verified_at = now();

        if($user->save())
        {
            $user['token'] = $user->createToken("EventMaster-User-$user->id")->accessToken; 
            if($user->isVendor())
            {
                $user['vendor_complete'] = (bool) Vendor::where('user_id', $user->id)->first()->complete;
            }
            else
            {
                $user['vendor_complete'] = false;
            }
            return response()->json(['user' => $user], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /** 
     * Forgot password api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function forgot(){
        $validator = Validator::make(request()->all(), [ 
            'phone' => 'required', 
        ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }

        $user = User::where(['phone' => request('phone')])->first(); 
        if(empty($user)) {
            return response()->json(['error' => 'No user record'], 400);
        }
        $otp = rand(100000, 999999);
        $user->otp = $otp;
        if ($user->save()) {
            $this->sendSms($user->phone, $otp);
            return response()->json(['message' => 'OTP sent'], $this->successStatus); 
        }

        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /**
     * 
     * Reset forgot password
     * 
     * @return \Illuminate\Http\Response
     */
    public function reset() {
        $validator = Validator::make(request()->all(), [ 
            'phone' => 'required', 
            'otp' => 'required', 
            'password' => 'required'
            ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }

        $user = User::where(['phone' => request('phone'), 'otp' => request('otp')])->first(); 
        if(empty($user)) {
            return response()->json(['error' => 'No user record'], 400);
        }
        $user->password = bcrypt(request('password'));
        $user->otp = NULL;
        if ($user->save()) {
            return response()->json(['message' => 'Password reset'], $this->successStatus); 
        }

        return response()->json(['error'=>'An error occurred'], 401);
    }

    /**
     * 
     * Logout user api
     * 
     * @return null
     */
    public function logout()
    { 
        if (Auth::check()) {
            Auth::user()->token()->revoke();
            return response()->json(['message' => 'Logout successfully'], $this->successStatus); 
        }
        return response()->json(['error' => 'No user'], 400); 
    }

    /**
     * Logout user everywhere
     */
    private function logoutAll(){
        DB::table('oauth_access_tokens')
        ->where('user_id', Auth::user()->id)
        ->update([
            'revoked' => true
        ]);
        return response()->json(['message' => 'Logout successfully'], $this->successStatus); 
    }
    
    /** 
     * Update user api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updateUser()
    {
        $auth = Auth::user();
        $input = request()->all();
        $user = User::findOrFail($auth->id);

        $user->email = $input['email'];
        $user->location = $input['location'];
        // $user->lat = $input['lat'];
        // $user->lng = $input['lng'];
        if ($user->save()) {
            return response()->json(['message' => 'User updated'], $this->successStatus); 
        }
        return response()->json(['error' => 'An error occurred'], 401); 
    }


    /** 
     * Update user password api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updatePassword()
    {
        $user = Auth::user();
        if (! Hash::check(request('old_password'), $user->password)) {
            return response()->json(['error' => 'You have entered a wrong password'], 401);
        }

        $user->password = bcrypt(request('new_password'));
        if ($user->save()) {
            return response()->json(['message' => 'Password updated'], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /** 
     * Delete user api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function delete()
    {
        $user = Auth::user();

        if ($user->delete()) {
            return response()->json(['success' => 'true'], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /**
     * Send OTP with Guzzle
     * 
     * @return \GuzzleHttp\response 
     */
    private function sendSms(String $contact, String $otp)
    {
        $url = "http://dstr.connectbind.com/sendsms?";

		$client = new Client([
	   		'base_uri'	=> $url,
	   		'debug' 	=> false,
	   		'timeout'  	=> 8.0,
	   		'headers' 	=> [
	   		        'Content-Type'  => 'text/plain;charset=UTF-8',
					'User-Agent' 	=> 'Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36'
				]
	   	]);
		$live_url = "username=ghgh-MobileApp&password=@Ironma1&type=0&dlr=0&destination=". $contact ."&source=E Masters&message=Your Event Masters code is " . $otp; 
        $response = $client->request('GET', $url, [
            'query' => $live_url,
            'allow_redirects' => false
        ]);
        $response = $response->getBody()->getContents();
    }
}
