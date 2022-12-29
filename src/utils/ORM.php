<?php

namespace CubaDevOps\CustomHtml\utils;

use Db;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\Dispatcher;
use PDO;

class ORM extends Capsule
{
    private function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        $this->addConnection([
                                 'driver' => DB::getInstance()->getLink()->getAttribute(PDO::ATTR_DRIVER_NAME),
                                 'host' => _DB_SERVER_,
                                 'database' => _DB_NAME_,
                                 'username' => _DB_USER_,
                                 'password' => _DB_PASSWD_,
                                 'prefix' => '',
                             ]);

        // Set the event dispatcher used by Eloquent models... (optional)
        $this->setEventDispatcher(new Dispatcher(new Container()));

        // Make this Capsule instance available globally via static methods... (optional)
        $this->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $this->bootEloquent();

        return $this;
    }

    public static function builder($connection = null): Builder
    {
        return (new self())->init()::schema($connection);
    }

    public static function getInstance(): ORM
    {
        return (new self())->init();
    }
}
