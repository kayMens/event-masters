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
                    ->join('users', 'users.id', '=', 'vendors.user_id')
                    ->select('users.id, users.name, users.phone, users.email, users.location', 'vendors.*')
                    ->where('vendors.complete', 1)
                    ->get();
        return response()->json($vendors, 200);
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
        $user->age = $input['age'];
        $user->brand = $input['brand'];
        $user->national_id = $input['national_id'];
        $user->national_id_num = $input['national_id_num'];
        $user->experience = $input['experience'];
        $user->engagement = $input['engagement'];
        $user->genre = $input['genre'];
        $user->craft = $input['craft'];
        if ($user->save()) {
            return response()->json(['message' => 'User updated'], $this->successStatus); 
        }
        return response()->json(['error' => 'An error occurred'], 401); 
    }

    /** 
     * Get user avatar api 
     * 
     * @param \Model\user
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function getAvatar(User $user)
    {
        if (empty($user)) {
            return response()->json(['error'=>'An error occurred'], 401); 
        }
        
        if (!empty($user->picture)) {
            $file = storage_path('app/avatar/' . $user->avatar);
            header('Content-Length: ' . filesize($file));
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            readfile($file);
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }

    /** 
     * Update avatar api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function updateAvatar()
    {
        $user = Auth::user();

        $file = request()->file('image_upload_file');
        $extension = $file->getClientOriginalExtension();
        $path = 'avatar';
        $name = 'avi_' . $user->id . '.' . $extension;
        $user->avatar = 'avi_' . $user->id . '.' . $extension;

        if ($file->storeAs($path, $name) && $user->save()) {
            return response()->json(['success' => $name], $this->successStatus); 
        }
        return response()->json(['error'=>'An error occurred'], 401); 
    }
}
