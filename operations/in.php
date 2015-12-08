<?php
	// in: 20 a
	//   read a character from the terminal and write its ascii code to <a>;
	//   it can be assumed that once input starts, it will continue until a
	//   newline is encountered; this means that you can safely read whole
	//   lines from the keyboard and trust that they will be fully read
	class Synacor_in implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;

			$in = ord(fread(STDIN, 1));

			if ($vm->isRegister($a)) {
				$vm->asRegister($a);
				$vm->set($a, $in);
			} else {
				$vm->decode($a);
				$vm->setData($a, $in);
			}
		}
		function code() { return 20; }
	}
