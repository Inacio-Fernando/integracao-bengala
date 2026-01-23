<?php

namespace IntegracaoBengala\Infra\Database;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Mavinoo\Batch\Batch;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->safeLoad();

class Database
{

	protected static $capsuleInstance;
	protected static $batchInstance;


	public static function setupEloquent()
	{

		$capsule = new Capsule;

		$capsule->addConnection([
			'driver' => $_ENV['DB_CONNECTION'],
			'host' => $_ENV['DB_HOST'],
			'port' => $_ENV['DB_PORT'],
			'database' => $_ENV['DB_DATABASE'],
			'username' => $_ENV['DB_USERNAME'],
			'password' => $_ENV['DB_PASSWORD'],
			'charset' => 'utf8',
			'collation' => 'utf8_unicode_ci',
		], 'mysql');

		// Make this Capsule instance available globally via static methods... (optional)
		$capsule->setAsGlobal();

		// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
		$capsule->bootEloquent();
		self::$capsuleInstance = $capsule;
	}
	protected static function setupBatch()
	{
		$capsule = self::getCapsule();
		$batch = new Batch($capsule->getDatabaseManager());
		self::$batchInstance = $batch;
	}

	public static function getCapsule()
	{
		if (!self::$capsuleInstance) {
			self::setupEloquent();
		}
		return self::$capsuleInstance;
	}

	public static function getBatch()
	{
		if (!self::$batchInstance) {
			self::setupBatch();
		}
		return self::$batchInstance;
	}

}


