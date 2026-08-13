<?php
namespace App\Http\Controllers;
use App\Models\PushDevice; use Illuminate\Http\Request;
class PushDeviceController extends Controller { public function store(Request $request) { $data=$request->validate(['token'=>['required','string','max:512']]); PushDevice::updateOrCreate(['token'=>$data['token']],['user_id'=>$request->user()->id,'platform'=>'web','last_seen_at'=>now()]); return response()->noContent(); } public function destroy(Request $request) { PushDevice::where('user_id',$request->user()->id)->where('token',$request->validate(['token'=>['required','string']])['token'])->delete(); return response()->noContent(); } }
