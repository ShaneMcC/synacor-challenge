<?php
	// or: 13 a b c
	//   stores into <a> the bitwise or of <b> and <c>
	class Synacor_or implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, $b | $c);
		}
		function code() { return 13; }
		function name() { return 'or'; }
	}
