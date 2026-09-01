<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) { $table->string('username', 40)->nullable()->unique()->after('name'); $table->string('email')->nullable()->change(); });
        foreach (DB::table('users')->orderBy('id')->get(['id','name','email']) as $user) {
            $base=Str::limit(Str::lower(Str::slug($user->name ?: Str::before((string)$user->email,'@'),'_')) ?: 'user',32,''); $username=$base; $suffix=1;
            while(DB::table('users')->whereRaw('lower(username) = ?',[$username])->exists()) $username=$base.'_'.++$suffix;
            DB::table('users')->where('id',$user->id)->update(['username'=>$username]);
        }
        Schema::table('users', fn(Blueprint $table)=>$table->string('username',40)->nullable(false)->change());
    }
    public function down(): void { Schema::table('users', function(Blueprint $table){$table->dropUnique(['username']);$table->dropColumn('username');$table->string('email')->nullable(false)->change();}); }
};
