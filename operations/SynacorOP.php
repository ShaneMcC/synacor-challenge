<?php
	interface SynacorOP {
		function args();
		function run($vm, $data);
		function code();
		function name();
	}
