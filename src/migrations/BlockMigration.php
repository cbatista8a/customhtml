<?php

namespace CubaDevOps\CustomHtml\migrations;

use CubaDevOps\CustomHtml\utils\ORM;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class BlockMigration extends Migration
{
    public static function up()
    {
        ORM::builder()->create('customhtml_blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('hook');
            $table->string('classes', 100)->nullable(true);
            $table->boolean('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });
        ORM::builder()->create('customhtml_blocks_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('block_id')->unsigned();
            $table->integer('lang_id');
            $table->longText('content');
            $table->timestamps();
            $table->foreign('block_id')->references('id')->on('customhtml_blocks')->onDelete('cascade');
        });
    }

    public static function down()
    {
        ORM::builder()->drop('customhtml_blocks_lang');
        ORM::builder()->drop('customhtml_blocks');
    }
}
