<?php
	// push: 2 a
	//   push <a> onto the stack
	class Synacor_push implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->decode($a);

			$vm->push($a);
		}
		function code() { return 2; }
		function name() { return 'push'; }
	}
