<?php
	// mult: 10 a b c
	//   store into <a> the product of <b> and <c> (modulo 32768)
	class Synacor_mult implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, ($b * $c) % 32768);
		}
		function code() { return 10; }
		function name() { return 'mult'; }
	}
