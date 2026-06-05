<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('jawaban_siswas',function(Blueprint $t){$t->timestamp('server_updated_at')->nullable()->after('client_updated_at')->index();});}public function down():void{Schema::table('jawaban_siswas',fn(Blueprint $t)=>$t->dropColumn('server_updated_at'));}};