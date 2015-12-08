<?php
	// and: 12 a b c
	//   stores into <a> the bitwise and of <b> and <c>
	class Synacor_and implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, $b & $c);
		}
		function code() { return 12; }
	}
