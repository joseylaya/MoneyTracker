<?php
namespace App\Services;
use App\Models\PushDevice; use App\Models\User; use Google\Auth\Credentials\ServiceAccountCredentials; use Illuminate\Support\Facades\Http;
class FirebasePush {
 public function send(User $user, string $title, string $body, array $data = []): void {
  $path = config('services.firebase.service_account'); if (app()->environment('testing') || !config('services.firebase.enabled') || !$path || !is_file($path)) return;
  $token = (new ServiceAccountCredentials(['https://www.googleapis.com/auth/firebase.messaging'], $path))->fetchAuthToken()['access_token'] ?? null; if (!$token) return;
  foreach (PushDevice::where('user_id',$user->id)->pluck('token') as $deviceToken) {
   $response = Http::withToken($token)->post('https://fcm.googleapis.com/v1/projects/'.config('services.firebase.project_id').'/messages:send', ['message'=>['token'=>$deviceToken,'notification'=>['title'=>$title,'body'=>$body],'data'=>collect($data)->map(fn($v)=>(string)$v)->all(),'webpush'=>['fcm_options'=>['link'=>config('app.url')]]]]);
   if ($response->failed() && in_array($response->json('error.status'), ['UNREGISTERED','INVALID_ARGUMENT'], true)) PushDevice::where('token',$deviceToken)->delete();
  }
 }
}
