<?php

namespace WikiExtensions;

use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\Runners\CliRunner;

/**
 * Version of CzProject\GitPhp's CLIRunner that adds `-c safe.directory=*` to
 * all commands so that dubious ownership is ignored
 */
class AlwaysSafeRunner implements IRunner {

	/** @var CliRunner */
	private $baseRunner;

	public function __construct( CliRunner $baseRunner ) {
		$this->baseRunner = $baseRunner;
	}

	/** @inheritDoc */
	public function run($cwd, array $args, ?array $env = NULL) {
		return $this->baseRunner->run(
			$cwd,
			['-c', 'safe.directory=*', ...$args],
			$env
		);
	}

	/** @inheritDoc */
	public function getCwd() {
		return $this->baseRunner->getCwd();
	}

}
