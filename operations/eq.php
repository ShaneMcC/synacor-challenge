<?php
	// eq: 4 a b c
	//   set <a> to 1 if <b> is equal to <c>; set it to 0 otherwise
	class Synacor_eq implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;
			$vm->debug('      EQ: ' . $vm->get($b) . ' == ' . $vm->get($c));
			$vm->set($a, ($vm->get($b) == $vm->get($c)) ? '1' : '0');
		}
		function code() { return 4; }
	}
