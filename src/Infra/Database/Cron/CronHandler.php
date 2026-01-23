<?php

namespace IntegracaoSimpatia\Infra\Database\Cron;

use stdClass;

abstract class CronHandler
{
	public $commands;

	public function __construct()
	{
		$this->commands = new stdClass();
	}

	function on(string $command, array $callback)
	{
		$this->commands->$command = $callback;
	}

	function type(array $params)
	{
		if(empty($params) || !isset($params['script'])) {
			return;
		}

		$script = $params['script'];
		
		if (empty($this->commands->$script) || count($this->commands->$script) < 2) {
			return;
		}
		
		$classInstance = $this->commands->$script[0];
		$function = (string) $this->commands->$script[1];

		if (!is_object($classInstance)) {
			return;
		}

		//Executar função
		$classInstance::$function($params);
	}

	abstract function write(string $text): void;

}