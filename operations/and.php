<?php
	// and: 12 a b c
	//   stores into <a> the bitwise and of <b> and <c>
	class Synacor_and implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->set($a, $vm->get($b) & $vm->get($c));
		}
		function code() { return 12; }
	}
