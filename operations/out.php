<?php
	// out: 19 a
	//   write the character represented by ascii code <a> to the terminal
	class Synacor_out implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			echo chr($a);
		}
		function code() { return 19; }
	}
