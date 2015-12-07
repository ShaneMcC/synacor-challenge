<?php
	// gt: 5 a b c
	//   set <a> to 1 if <b> is greater than <c>; set it to 0 otherwise
	class Synacor_gt implements SynacorOP {
		function args() { return 3; }
		function run($vm, $data) {
			list($a, $b, $c) = $data;

			$vm->debug('      GT: ' . $vm->get($b) . ' > ' . $vm->get($c));

			$vm->set($a, $vm->get($b) > $vm->get($c) ? '1' : '0');
		}
		function code() { return 5; }
	}
