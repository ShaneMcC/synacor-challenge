<?php
	// or: 13 a b c
	//   stores into <a> the bitwise or of <b> and <c>
	class Synacor_or implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->set($a, $vm->get($b) | $vm->get($c));
		}
		function code() { return 13; }
	}
