<?php
	// add: 9 a b c
	//   assign into <a> the sum of <b> and <c> (modulo 32768)
	class Synacor_add implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->set($a, ($vm->get($b) + $vm->get($c)) % 32768);
		}
		function code() { return 9; }
	}
