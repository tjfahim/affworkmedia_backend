<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAffiliateColumnsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add affiliate columns if they don't exist
          
            if (!Schema::hasColumn('users', 'total_clicks')) {
                $table->integer('total_clicks')->default(0)->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'unique_clicks')) {
                $table->integer('unique_clicks')->default(0)->after('total_clicks');
            }
            if (!Schema::hasColumn('users', 'total_sales')) {
                $table->integer('total_sales')->default(0)->after('unique_clicks');
            }
            if (!Schema::hasColumn('users', 'total_earnings')) {
                $table->decimal('total_earnings', 10, 2)->default(0)->after('total_sales');
            }
        
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'total_clicks',
                'unique_clicks',
                'total_sales',
                'total_earnings',
            ]);
        });
    }
}