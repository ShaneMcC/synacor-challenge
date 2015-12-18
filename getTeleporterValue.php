<?php

/*
DUMP:     5483 |    set {R1}, 4
DUMP:     5486 |    set {R2}, 1
DUMP:     5489 |   call 6027
DUMP:     5491 |     eq {R2}, {R1}, 6
DUMP:     5495 |     jf {R2}, 5579

DUMP:     6027 |     jt {R1}, 6035
DUMP:     6030 |    add {R1}, {R2}, 1
DUMP:     6034 |    ret

DUMP:     6035 |     jt {R2}, 6048
DUMP:     6038 |    add {R1}, {R1}, 32767
DUMP:     6042 |    set {R2}, {R8}
DUMP:     6045 |   call 6027
DUMP:     6047 |    ret

DUMP:     6048 |   push {R1}
DUMP:     6050 |    add {R2}, {R2}, 32767
DUMP:     6054 |   call 6027
DUMP:     6056 |    set {R2}, {R1}
DUMP:     6059 |    pop {R1}
DUMP:     6061 |    add {R1}, {R1}, 32767
DUMP:     6065 |   call 6027
DUMP:     6067 |    ret
*/


/**
 * DUMP:     5483 |    set {R1}, 4
 * DUMP:     5486 |    set {R2}, 1
 * DUMP:     5489 |   call 6027
 * DUMP:     5491 |     eq {R2}, {R1}, 6
 * DUMP:     5495 |     jf {R2}, 5579
 */
function call5483($r1 = 4, $r2 = 1, $r8 = 0) {
	$stack = array();
	call6027($r1, $r2, $r8, $stack);
	$r2 = ($r1 === 6) ? 1 : 0;
	return ($r2 === 0);
}

/**
 * DUMP:     6027 |     jt {R1}, 6035
 * DUMP:     6030 |    add {R1}, {R2}, 1
 * DUMP:     6034 |    ret
*/
function call6027(&$r1, &$r2, &$r8, &$stack) {
	if ($r1 != 0) {
		call6035($r1, $r2, $r8, $stack);
	} else {
		$r1 = ($r2 + 1)  % 32768;
	}
}

/**
 * DUMP:     6035 |     jt {R2}, 6048
 * DUMP:     6038 |    add {R1}, {R1}, 32767
 * DUMP:     6042 |    set {R2}, {R8}
 * DUMP:     6045 |   call 6027
 * DUMP:     6047 |    ret
*/
function call6035(&$r1, &$r2, &$r8, &$stack) {
	if ($r2 != 0) {
		call6048($r1, $r2, $r8, $stack);
	} else {
		$r2 = $r8;
		call6027($r1, $r2, $r8, $stack);
	}
}

/**
 * DUMP:     6048 |   push {R1}
 * DUMP:     6050 |    add {R2}, {R2}, 32767
 * DUMP:     6054 |   call 6027
 * DUMP:     6056 |    set {R2}, {R1}
 * DUMP:     6059 |    pop {R1}
 * DUMP:     6061 |    add {R1}, {R1}, 32767
 * DUMP:     6065 |   call 6027
 * DUMP:     6067 |    ret
*/
function call6048(&$r1, &$r2, &$r8, &$stack) {
	array_push($stack, $r1);
	$r2 = ($r2 + 32767)  % 32768;
	call6027($r1, $r2, $r8, $stack);
	$r2 = $r1;
	$r1 = array_pop($stack);
	$r1 = ($r1 + 32767)  % 32768;
	call6027($r1, $r2, $r8, $stack);
}




function test($val) {
	$r1 = 4;
	$r2 = 1;
	$r8 = $val;
	$result = call5483($r1, $r2, $r8);
	return $result;
}


for ($i = 0; $i <= 32768; $i++) {
	echo $i, ": ";
	if (test($i)) {
		echo 'Incorrect.', "\n";
	} else {
		die('Got value: ' . $i . "\n");
	}
}
