<?php
	// set: 1 a b
	//   set register <a> to the value of <b>
	class Synacor_set implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->set($a, $vm->get($b));
		}
		function code() { return 1; }
	}
