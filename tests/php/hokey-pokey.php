<?php 

function ciede_2000($l_1, $a_1, $b_1, $l_2, $a_2, $b_2) {
	// Working with the CIEDE2000 color-difference formula.
	// k_l, k_c, k_h are parametric factors to be adjusted according to
	// different viewing parameters such as textures, backgrounds...
	$k_l = $k_c = $k_h = 1.0;
	$n = (hypot($a_1, $b_1) + hypot($a_2, $b_2)) * 0.5;
	$n = $n * $n * $n * $n * $n * $n * $n;
	// A factor involving chroma raised to the power of 7 designed to make
	// the influence of chroma on the total color difference more accurate.
	$n = 1.0 + 0.5 * (1.0 - sqrt($n / ($n + 6103515625.0)));
	// hypot calculates the Euclidean distance while avoiding overflow/underflow.
	$c_1 = hypot($a_1 * $n, $b_1);
	$c_2 = hypot($a_2 * $n, $b_2);
	// atan2 is preferred over atan because it accurately computes the angle of
	// a point (x, y) in all quadrants, handling the signs of both coordinates.
	$h_1 = atan2($b_1, $a_1 * $n);
	$h_2 = atan2($b_2, $a_2 * $n);
	$h_1 += 2.0 * M_PI * ($h_1 < 0.0);
	$h_2 += 2.0 * M_PI * ($h_2 < 0.0);
	$n = abs($h_2 - $h_1);
	// Cross-implementation consistent rounding.
	if (M_PI - 1E-14 < $n && $n < M_PI + 1E-14)
		$n = M_PI;
	// When the hue angles lie in different quadrants, the straightforward
	// average can produce a mean that incorrectly suggests a hue angle in
	// the wrong quadrant, the next lines handle this issue.
	$h_m = 0.5 * $h_1 + 0.5 * $h_2;
	$h_d = ($h_2 - $h_1) * 0.5;
	if (M_PI < $n) {
		if (0.0 < $h_d)
			$h_d -= M_PI;
		else
			$h_d += M_PI;
		$h_m += M_PI;
	}
	$p = (36.0 * $h_m - 55.0 * M_PI);
	$n = ($c_1 + $c_2) * 0.5;
	$n = $n * $n * $n * $n * $n * $n * $n;
	// The hue rotation correction term is designed to account for the
	// non-linear behavior of hue differences in the blue region.
	$r_t = -2.0 * sqrt($n / ($n + 6103515625.0))
					   * sin(M_PI / 3.0 * exp($p * $p / (-25.0 * M_PI * M_PI)));
	$n = ($l_1 + $l_2) * 0.5;
	$n = ($n - 50.0) * ($n - 50.0);
	// Lightness.
	$l = ($l_2 - $l_1) / ($k_l * (1.0 + 0.015 * $n / sqrt(20.0 + $n)));
	// These coefficients adjust the impact of different harmonic
	// components on the hue difference calculation.
	$t = 1.0 + 0.24 * sin(2.0 * $h_m + M_PI_2)
					 + 0.32 * sin(3.0 * $h_m + 8.0 * M_PI / 15.0)
					 - 0.17 * sin($h_m + M_PI / 3.0)
					 - 0.20 * sin(4.0 * $h_m + 3.0 * M_PI_2 / 10.0);
	$n = $c_1 + $c_2;
	// Hue.
	$h = 2.0 * sqrt($c_1 * $c_2) * sin($h_d) / ($k_h * (1.0 + 0.0075 * $n * $t));
	// Chroma.
	$c = ($c_2 - $c_1) / ($k_c * (1.0 + 0.0225 * $n));
	// Returning the square root ensures that the result represents
	// the "true" geometric distance in the color space.
	return sqrt($l * $l + $h * $h + $c * $c + $c * $h * $r_t);
}

function prepare_values($n_lines = 10000) {
	$filename = './values-php.txt' ;
	echo "prepare_values('$filename', $n_lines)\n" ;
	$fp = fopen($filename, 'w');
	for($i = 0; $i < $n_lines; ++$i){
		$values = [
			round(100. * (mt_rand() / mt_getrandmax()), 2),
			round(255. * (mt_rand() / mt_getrandmax()) - 128., 2),
			round(255. * (mt_rand() / mt_getrandmax()) - 128., 2),
			round(100. * (mt_rand() / mt_getrandmax()), 2),
			round(255. * (mt_rand() / mt_getrandmax()) - 128., 2),
			round(255. * (mt_rand() / mt_getrandmax()) - 128., 2),
		];
		if ($i & 1)
			$values = array_map('round', $values) ;
		else if ($i % 1000 == 0)
			echo '.' ;
		$values[ ] = ciede_2000(...$values);
		fputcsv($fp, $values);
	}
	fclose($fp);
}

function compare_values($extension = 'php'){
	$i = $n_errors = 0 ;
	$filename = "./../$extension/values-$extension.txt" ;
	echo "compare_values('$filename')\n" ;
	$fp = fopen($filename, 'r');
	while($arr = fgetcsv($fp, 1024, ',')){
		++$i ;
		$arr = array_map('floatval', $arr);
		$delta_e = array_pop($arr) ;
		$res = ciede_2000(...$arr) ;
		$abs_err = abs($delta_e - $res);
		if (!is_finite($delta_e) || !is_finite($res) || 1e-10 < $abs_err) {
			echo json_encode([
				'submited' => $arr,
				'expected' => $delta_e,
				'computed' => $res,
				'abs_err' => $abs_err], JSON_PRETTY_PRINT), "\n" ;
				if (++$n_errors == 10)
					break ;
		} else if($i % 1000 === 0){
			echo '.' ;
			fflush(STDOUT);
		}
	}
	fclose($fp) ;
}

if (ctype_alpha($argv[1] ?? '-'))
	compare_values($argv[1]);
else
	prepare_values((int)($argv[1] ?? 10000));

