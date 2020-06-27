<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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
        //select if 24hrs has not expired
        $vendors = DB::table('vendors')
                    ->join('users', 'vendors.user_id', '=', 'users.id')
                    ->select('vendors.*', 'users.name as username', 'users.phone as userphone', 'users.email as useremail')
                    ->where('vendors.complete', '1')
                    ->get();
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
        $vendor->logo = 'avi_' . $vendor->id . '.' . $extension;

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
        $vendor->header = 'header_' . $vendor->id . '.' . $extension;

        if ($file->storeAs($path, $name) && $vendor->save()) {
            return response()->json(['success' => $name], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }
}
