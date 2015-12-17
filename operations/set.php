<?php
	// set: 1 a b
	//   set register <a> to the value of <b>
	class Synacor_set implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->asRegister($a);
			$vm->decode($b);

			$vm->set($a, $b);
		}
		function code() { return 1; }
		function name() { return 'set'; }
	}
