<?php
	// not: 14 a b
	//   stores 15-bit bitwise inverse of <b> in <a>
	class Synacor_not implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->asRegister($a);
			$vm->decode($b);

			$vm->set($a, (~ $b & 0x7FFF));
		}
		function code() { return 14; }
	}
