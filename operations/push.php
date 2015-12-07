<?php
	// push: 2 a
	//   push <a> onto the stack
	class Synacor_push implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->push($vm->get($a));
		}
		function code() { return 2; }
	}
