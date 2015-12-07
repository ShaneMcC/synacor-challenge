<?php
	// mod: 11 a b c
	//   store into <a> the remainder of <b> divided by <c>
	class Synacor_mod implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->set($a, $vm->get($b) % $vm->get($c));
		}
		function code() { return 11; }
	}
