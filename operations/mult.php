<?php
	// mult: 10 a b c
	//   store into <a> the product of <b> and <c> (modulo 32768)
	class Synacor_mult implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->set($a, ($vm->get($b) * $vm->get($c)) % 32768);
		}
		function code() { return 10; }
	}
