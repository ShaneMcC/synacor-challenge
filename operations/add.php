<?php
	// add: 9 a b c
	//   assign into <a> the sum of <b> and <c> (modulo 32768)
	class Synacor_add implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, ($b + $c) % 32768);
		}
		function code() { return 9; }
		function name() { return 'add'; }
	}
