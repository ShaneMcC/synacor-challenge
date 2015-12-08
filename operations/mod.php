<?php
	// mod: 11 a b c
	//   store into <a> the remainder of <b> divided by <c>
	class Synacor_mod implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->asRegister($a);
			$vm->decode($b);
			$vm->decode($c);

			$vm->set($a, $b % $c);
		}
		function code() { return 11; }
	}
