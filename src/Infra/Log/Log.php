<?php
namespace IntegracaoSimpatia\Infra\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Log {

	protected static $appErrorInstance;
	protected static $databaseErrorInstance;
	protected static $appSuccessInstance;
	
	/**
	 * Method to return the Monolog instance
	 *
	 * @return \Monolog\Logger
	 */
	static public function channel($file = 'application')
	{

		if (! self::$appErrorInstance) {
			self::configureAppErrorInstance();
		}
		if (! self::$databaseErrorInstance) {
			self::configureDatabaseErrorInstance();
		}
		if (! self::$appSuccessInstance) {
			self::configureAppSuccessInstance();
		}

		if($file == 'database-error') {
			return self::$databaseErrorInstance;
		}
		
		if($file == 'application-error') {
			return self::$appErrorInstance;
		}

		return self::$appSuccessInstance;
	}

	/**
	 * Configure Monolog to use a rotating files system.
	 *
	 * @return Logger
	 */
	protected static function configureAppErrorInstance()
	{
		$dateFormat = "H:i:s";
		$output = "[%datetime%] %level_name%: %message% %context% %extra%\n";
		// $date = date('d-m-Y');

        $log = new Logger('application-error');

//		$handler = new StreamHandler("logs/{$date}_application.log");
		$handler = new RotatingFileHandler("logs/application-error.log", 3, Logger::DEBUG, true, 0777); 
		$formatter = new LineFormatter($output, $dateFormat, false, true);
		$handler->setFormatter($formatter);
        $log->pushHandler($handler);
		self::$appErrorInstance = $log;
	}

	protected static function configureDatabaseErrorInstance()
	{
		$dateFormat = "H:i:s";
		$output = "[%datetime%] %level_name%: %message% %context% %extra%\n";
		// $date = date('d-m-Y');

		$log = new Logger('database-error');
		// $handler = new StreamHandler("logs/{$date}_database.log");
		$handler = new RotatingFileHandler("logs/database-error.log", 3, Logger::DEBUG, true, 0777); 
		$formatter = new LineFormatter($output, $dateFormat, false, true);
		$handler->setFormatter($formatter);
        $log->pushHandler($handler);
		self::$databaseErrorInstance = $log;
	}
	protected static function configureAppSuccessInstance()
	{
		$dateFormat = "H:i:s";
		$output = "[%datetime%] %level_name%: %message% %context% %extra%\n";
		// $date = date('d-m-Y');

		$log = new Logger('application');
		// $handler = new StreamHandler("logs/{$date}_database.log");
		$handler = new RotatingFileHandler("logs/application.log", 3, Logger::DEBUG, true, 0777); 
		$formatter = new LineFormatter($output, $dateFormat, false, true);
		$handler->setFormatter($formatter);
        $log->pushHandler($handler);
		self::$appSuccessInstance = $log;
	}
	
	public static function debug($message, array $context = [], $file = "application"){
			self::channel($file)->debug($message, $context);
	}

	public static function info($message, array $context = [], $file = "application"){
			self::channel($file)->info($message, $context);
	}

	public static function notice($message, array $context = [], $file = "application-error"){
			self::channel($file)->notice($message, $context);
	}

	public static function warning($message, array $context = [], $file = "application-error"){
			self::channel($file)->warning($message, $context);
	}

	public static function error($message, array $context = [], $file = "application-error"){
			self::channel($file)->error($message, $context);
	}

	public static function critical($message, array $context = [], $file = "application-error"){
			self::channel($file)->critical($message, $context);
	}

	public static function alert($message, array $context = [], $file = "application-error"){
			self::channel($file)->alert($message, $context);
	}

	public static function emergency($message, array $context = [], $file = "application-error"){
			self::channel($file)->emergency($message, $context);
	}

}