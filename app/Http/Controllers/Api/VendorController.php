<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\User;
use App\Vendor;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class VendorController extends Controller
{
    public $successStatus = 200;
    
    /**
     *  Vendors
     * 
     * @return \Illumininate\Http\Response
     */
    public function vendor() {
        // $vendors = DB::table('vendors')
                    // ->join('users', 'vendors.user_id', '=', 'users.id')
                    // ->select('vendors.*', 'users.name as username', 'users.phone as userphone', 'users.email as useremail')
                    // ->where('vendors.complete', '1')
                    // ->get();
        $vendors = Vendor::where('complete', '1')->get();
        foreach ($vendors as $key => $value) {
            $vendors[$key]->user = User::where('id', $value->user_id)->first();
            $vendors[$key]->user->vendor_complete = true;
            $vendors[$key]->user->token = '';
            $vendors[$key]->lat = $vendors[$key]->lat == NULL ? 0.0 : doubleval($vendors[$key]->lat);
            $vendors[$key]->lng = $vendors[$key]->lng == NULL ? 0.0 : doubleval($vendors[$key]->lng);
            unset($vendors[$key]->user_id);
            unset($vendors[$key]->dir_path);
            unset($vendors[$key]->created_at);
            unset($vendors[$key]->updated_at);
            unset($vendors[$key]->user->otp);
            unset($vendors[$key]->user->created_at);
            unset($vendors[$key]->user->updated_at);
            unset($vendors[$key]->user->phone_verified_at);
        }
        return response()->json($vendors, 200);
    }

    /** 
     * Vendor account api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function account()
    {
        $auth = Auth::user();
        if(! $auth->isVendor()){
            abort(403, 'User not having needed permission');
        }

        $vendor = Vendor::where('user_id', $auth->id)->first();

        if ($vendor != null) {
            $vendor['user'] = $auth;
            return response()->json($vendor, $this->successStatus); 
        }
        return response()->json(['error' => 'An error occurred'], 401); 
    }

        
    /** 
     * Update vendor api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function update()
    {
        $auth = Auth::user();
        if(! $auth->isVendor()){
            abort(403, 'User not having needed permission');
        }
        $validator = Validator::make(request()->all(), [ 
            'name' => 'required', 
            'category' => 'required', 
            'service' => 'required',
            ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }
        $input = request()->all();
        $vendor = Vendor::where('user_id', $auth->id)->firstOrFail();

        $vendor->name = $input['name'];
        $vendor->phone = $input['phone'];
        $vendor->email = $input['email'];
        $vendor->location = $input['location'];
        $vendor->lat = $input['lat'];
        $vendor->lng = $input['lng'];
        $vendor->about = $input['about'];
        $vendor->category = $input['category'];
        $vendor->service = $input['service'];
        $vendor->complete = true;

        if ($vendor->save()) {
            return response()->json(['message' => 'Vendor updated'], $this->successStatus); 
        }
        return response()->json(['error' => 'An error occurred'], 401); 
    }

    /**
     *  Vendor requests
     * 
     * @return \Illumininate\Http\Response
     */
    public function quoteRequest() {
        $user = Auth::user();

        if(! $user->isVendor()) {
            abort(403, 'User not having needed permission');
        }
        $vendor = Vendor::where('user_id', $user->id)->firstOrFail();

        $request = DB::table('quotes')
                    ->join('events', 'quotes.event_id', '=','events.id')
                    ->join('users', 'events.user_id', '=','users.id')
                    ->select('events.*', 'quotes.quote', 'quotes.service', 'quotes.book', 'users.name', 'users.email', 'users.phone')
                    ->where('quotes.vendor_id', $vendor->id)
                    ->orderby('quotes.id', 'desc')
                    ->get();

        foreach ($request as $key => $value) {
            unset($request[$key]->created_at);
            unset($request[$key]->updated_at);
        } 
        return response()->json($request, 200);
    }

    /**
     * Vendor set quote
     * 
     * @return \Illuminate\Http\Response
     */
    public function setQuote() {
        $user = Auth::user();

        if(!$user->isVendor()) {
            abort(403, 'User not having needed permission');
        }

        $validator = Validator::make(request()->all(), [ 
            'vendor_id' => 'required', 
            'event_id' => 'required', 
            'quote' => 'required', 
        ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }
        $vendor = Vendor::where('user_id', $user->id)->firstOrFail();
        
        $input = request()->all();
        $request = DB::table('quotes')
                        ->where([
                            'vendor_id' => $vendor->id,
                            'event_id'  => $input['event_id'],
                            'book' => false
                        ])
                       ->update([
                           'quote' => $input['quote'],
                           'updated_at' => now()
                       ]);
        if($request){
            return response()->json(['message'=> 'Quote updated'], $this->successStatus); 
        }

        return response()->json(['error' => 'Quote not updated'], 401);            
    }

    /** 
     * Get vendor logo api 
     * 
     * @param \Model\user
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function logo(Vendor $vendor)
    {
        if (empty($vendor)) {
            return response()->json(['error'=>'An error occurred'], 401); 
        }
        
        if (!empty($vendor->logo)) {
            $file = storage_path("app/vendor/$vendor->dir_path/$vendor->logo");
            header('Content-Length: ' . filesize($file));
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            readfile($file);
        }
        return response()->json(['error'=>'No logo present'], 404); 
    }

    /** 
     * Get vendor header api 
     * 
     * @param \Model\user
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function header(Vendor $vendor)
    {
        if (empty($vendor)) {
            return response()->json(['error'=>'An error occurred'], 401); 
        }
        
        if (!empty($vendor->header)) {
            $file = storage_path("app/vendor/$vendor->dir_path/$vendor->header");
            header('Content-Length: ' . filesize($file));
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            readfile($file);
        }
        return response()->json(['error'=>'No header not present'], 404); 
    }

    /** 
     * Update logo api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updateLogo(Vendor $vendor)
    {
        $file = request()->file('image_upload');
        $extension = $file->getClientOriginalExtension();
        $path = 'vendor/'. $vendor->dir_path .'/';
        $name = 'avi_' . $vendor->id . '.' . $extension;
        $vendor->logo = $name;
        $vendor->logo_at = time();

        if ($file->storeAs($path, $name) && $vendor->save()) {
            return response()->json(['success' => $name], $this->successStatus); 
        }

        return response()->json(['error'=>'An error occurred'], 401); 
    }
    
    /** 
     * Update logo api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updateLogoSetup()
    {
        $user = Auth::user();

        if(! $user->isVendor()) {
            abort(403, 'User not having needed permission');
        }
        $vendor = Vendor::where('user_id', $user->id)->firstOrFail();
        
        $file = request()->file('image_upload');
        $extension = $file->getClientOriginalExtension();
        $path = 'vendor/'. $vendor->dir_path .'/';
        $name = 'avi_' . $vendor->id . '.' . $extension;
        $vendor->logo = $name;
        $vendor->logo_at = time();

        if ($file->storeAs($path, $name) && $vendor->save()) {
            return response()->json(['success' => $name], $this->successStatus); 
        }

        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /** 
     * Update header api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updateHeader(Vendor $vendor)
    {
        $file = request()->file('image_upload');
        $extension = $file->getClientOriginalExtension();
        $path = 'vendor/'. $vendor->dir_path .'/';
        $name = 'header_' . $vendor->id . '.' . $extension;
        $vendor->header = $name;
        $vendor->header_at = time();


        if ($file->storeAs($path, $name) && $vendor->save()) {
            // if(! empty($vendor->header)){
                // Storage::delete($path . $vendor->header);
                // unlink(storage_path('app/' . $path . $vendor->header)); //alternative to Storage::delete()
            // }
            // $vendor->header = $name;
            // $vendor->save();
            return response()->json(['success' => $name], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }
}
