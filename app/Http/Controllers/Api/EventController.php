<?php

namespace App\Http\Controllers\Api;

use App\Event;
use App\User;
use App\Vendor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    public $successStatus = 200;
   
    /**
     *  User events
     * 
     * @return \Illumininate\Http\Response
     */
    public function event() {
        $user = Auth::user();

        if(! $user->isUser()) {
            abort(403, 'User not having needed permission');
        }
        $event = Event::where('user_id', $user->id)->orderBy('id', 'desc')->get();
        return response()->json($event, 200);
    }

    /**
     *  User event names
     * 
     * @return \Illumininate\Http\Response
     */
    public function user() {
        $user = Auth::user();

        if(! $user->isUser()) {
            abort(403, 'User not having needed permission');
        }
        $events = DB::table('events')
                    ->join('quotes', 'events.id', '=', 'quotes.event_id')
                    ->select('quotes.book', 'events.id', 'events.title')
                    ->where('events.user_id', $user->id)
                    ->where('quotes.book', false)
                    ->orderBy('events.title', 'asc')
                    ->get();

        return response()->json($events, 200);
    }

    /**
     *  User quotes
     * 
     * @return \Illumininate\Http\Response
     */
    public function quote(int $id) {
        $user = Auth::user();

        if(! $user->isUser()) {
            abort(403, 'User not having needed permission');
        }
        // $quote = DB::table('quotes')
                    // ->join('vendors', 'quotes.vendor_id', '=', 'vendors.id')
                    // ->select('quotes.*', 'vendors.name', 'vendors.phone', 'vendors.email')
                    // ->where('quotes.event_id', $id)
                    // ->orderBy('quotes.id', 'desc')
                    // ->get();
        $quote = DB::table('quotes')
                    ->where('quotes.event_id', $id)
                    ->orderBy('quotes.id', 'desc')
                    ->get();
        foreach ($quote as $key => $value) {
            $quote[$key]->vendor = Vendor::find($value->vendor_id);
        }
        return response()->json($quote, 200);
    }

    /**
     * User creates event
     * 
     * @return \Illuminate\Http\Response
     */
    public function create() {
        $user = Auth::user();

        if(!$user->isUser()) {
            abort(403, 'User not having needed permission');
        }

        $validator = Validator::make(request()->all(), [ 
            'title' => 'required', 
            'venue' => 'required', 
            'lat' => 'required', 
            'lng' => 'required', 
            'start_at' => 'required',
            'end_at' => 'required',
            'budget' => 'required',
            'guest' => 'required',
            ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }

        $input = request()->all();
        $input['user_id'] = $user->id;
        $task = Event::create($input);

        return response()->json(['message'=> 'Event created'], $this->successStatus); 
    }
    
    /**
     * User creates event
     * 
     * @return \Illuminate\Http\Response
     */
    public function requestQuote() {
        $user = Auth::user();

        if(!$user->isUser()) {
            abort(403, 'User not having needed permission');
        }

        $validator = Validator::make(request()->all(), [ 
            'vendor_id' => 'required', 
            'event_id' => 'required', 
            'quote' => 'required', 
            'service' => 'required', 
            ]);
        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 401);            
        }
        $exist = DB::table('quotes')
                    ->where('vendor_id', request('vendor_id'))
                    ->where('event_id', request('event_id'))
                    ->where('quote', request('quote'))
                    ->where('service', request('service'))
                    ->count();
        if($exist){
            return response()->json(['message' => 'Records exist'], 200);            
        }

        $input = request()->all();
        $request = DB::table('quotes')
                       ->insertOrIgnore($input);
        if($request){
            return response()->json(['message'=> 'Request created'], $this->successStatus); 
        }

        return response()->json(['error' => 'Request not made'], 401);            
    }

    /**
     * Admin assign task to artiste
     * 
     * @return \Illuminate\Http\Response
     */
    public function book() {
        $user = Auth::user();
        
        if(!$user->isUser()) {
            abort(403, 'User not having needed permission');
        }
        $validator = Validator::make(request()->all(), [ 
            'id' => 'required', 
            'vendor_id' => 'required'
        ]);

        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 400);            
        }
        $request = DB::table('quotes')
                    ->where([
                        'id' => request('id'), 
                        'vendor_id' => request('vendor_id')
                        ])
                    ->update([
                        'book' => true, 
                        'updated_at' => now()
                        ]);
        if($request) {
            $vendor = Vendor::where(['id' => request('vendor_id')])->first();
            //send sms to vendor

            return response()->json(['message' => 'Vendor booked'], $this->successStatus);
        }

        return response()->json(['error' => 'An error occurred'], 400); 
    }

    /**
     * User accepts assigned task
     * 
     * @return \Illuminate\Http\Response
     */
    public function accept() {
        $user = Auth::user();

        if(! $user->isArtiste()) {
            abort(403, 'User not having needed permission');
        }

        $validator = Validator::make(request()->all(), [ 
            'task_id' => 'required', 
        ]);

        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 400);            
        }

        $task = DB::table('user_task')
                    ->where([
                        'id' => request('task_id'),
                        'user_id' => $user->id
                    ])
                    ->update([
                        'status' => 1,
                        'updated_at' => now(),
                    ]);
        if($task){
            //send sms to admin
            return response()->json(['message' => 'Task accepted'], $this->successStatus); 
        }

        return response()->json(['error' => 'An error occurred'], 400); 
    }

    /**
     * Admin confirms user acceptance
     * 
     * @return \Illuminate\Http\Response
     */
    public function confirm() {
        $user = Auth::user();

        if(!$user->isAdmin()) {
            abort(403, 'User not having needed permission');
        }

        $validator = Validator::make(request()->all(), [ 
            'task_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 400);            
        }

        $task = DB::table('user_task')
                    ->where('id', request('task_id'))
                    ->update([
                        'confirm' => 1, 
                        'updated_at' => now(),
                    ]);
        if($task){
            //send sms to artiste
            $artiste = User::where('id', request('user_id'))->first();

            return response()->json(['message' => 'Task confirmed'], $this->successStatus); 
        }
        return response()->json(['message' => 'Record modified'], 205); 
    }

    /**
     * Send SMS with Guzzle
     * 
     * @return \GuzzleHttp\response 
     */
    private function sendSMS(String $contact, String $msg)
    {
        $client = new Client(); //GuzzleHttp\Client
        $response = $client->get('http://client.bulksmsgh.com/smsapi?', [
            'form_params' => [
                'key' => 'value',
                'msg' => urlencode($msg),
                'to'  => urlencode($contact),
                'sender_id' => 'MusikEmpire'
            ]
        ]);
        $response = $response->getBody()->getContents();
	    //$data = (string) $response->getBody();

    }
}
