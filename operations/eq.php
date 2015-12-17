<?php
	// eq: 4 a b c
	//   set <a> to 1 if <b> is equal to <c>; set it to 0 otherwise
	class Synacor_eq implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, $b == $c ? '1' : '0');
		}
		function code() { return 4; }
		function name() { return 'eq'; }
	}
