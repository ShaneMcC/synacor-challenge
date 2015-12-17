<?php
	// halt: 0
	//   stop execution and terminate the program
	class Synacor_halt implements SynacorOP {
		function args() { return 0; }
		function run($vm, $data) {
			$vm->haltvm('HALT');
		}
		function code() { return 0; }
		function name() { return 'halt'; }
	}
