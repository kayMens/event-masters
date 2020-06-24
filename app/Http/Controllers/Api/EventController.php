<?php

namespace App\Http\Controllers\Api;

use App\Event;
use App\User;
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
        //select if 24hrs has not expired
        $task = DB::table('events')
                    ->join('quotes', 'quotes.event_id', '=', 'events.id')
                    ->select('events.*', 'quotes.*')
                    ->where('events.user_id', $user->id)
                    ->get();
        return response()->json($task, 200);
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
            'details' => 'required',
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
     * Hire deletes task if not asssgined
     * 
     * @return \Illuminate\Http\Response
     */
    public function delete() {

    }

    /**
     * Admin assign task to artiste
     * 
     * @return \Illuminate\Http\Response
     */
    public function assign() {
        $user = Auth::user();
        
        if(!$user->isAdmin()) {
            abort(403, 'User not having needed permission');
        }
        $validator = Validator::make(request()->all(), [ 
            'task_id' => 'required', 
            'user_id' => 'required'
        ]);

        if ($validator->fails()) { 
            return response()->json(['error' => $validator->errors()], 400);            
        }
        $assign = DB::table('user_task')->insertOrIgnore([
                    'task_id'    => request('task_id'),
                    'user_id'    => request('user_id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        if($assign) {
            $artiste = User::where(['id' => request('user_id')])->first();
            //send sms to artiste

            return response()->json(['message' => 'Task assigned'], $this->successStatus);
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
