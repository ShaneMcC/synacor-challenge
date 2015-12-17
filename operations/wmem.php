<?php
	// wmem: 16 a b
	//   write the value from <b> into memory at address <a>
	class Synacor_wmem implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->decode($a);
			$vm->decode($b);

			$vm->setData($a, $b);
		}
		function code() { return 16; }
		function name() { return 'wmem'; }
	}
