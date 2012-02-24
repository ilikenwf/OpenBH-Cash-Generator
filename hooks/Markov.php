<?php
/******** Syndk8's OpenBH *********
 *
 * This program is free software
 * licensed under the GPLv2 license.
 * You may redistribute it and/or
 * modify it under the terms of
 * the GPLv2 license (see license.txt)
 *
 * Warning:
 * OpenBH is for educational use
 * Use OpenBH at your own risk !
 *
 * Credits:
 * https://www.syndk8.com/openbh/people.html
 *
 ********************************/


/**
 *   adhooks/Markov.php
 * 
 * 	 Ported from YACG 3.9.0
 * 
 * 	 Portions Copyright (C) 2009  busin3ss [at] gmail [dot] com
 *	 This program is free software; you can redistribute it and/or
 *	 modify it under the terms of the GNU General Public License
 *	 as published by the Free Software Foundation; either version 2
 *	 of the License, or (at your option) any later version.
 *
 *	 This program is distributed in the hope that it will be useful,
 *	 but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	 GNU General Public License for more details.
 * 
 * 	 Markov chains the $content
 *
 *   @todo Tweak/improve/rewrite...I'm not an expert on Markov, need
 * 		   to study the algo sometime...I'm really just ripping 
 * 		   YACG's code off
 *   @author ilikenwf
 */
 
 class Markov implements HookBase
 {
	 function EnrichContent($content, $gran = 5, $num = 200, $letters_line = 65) {		
		$content = preg_replace('/\s\s+/', ' ', $content);
		$content = preg_replace('/\n|\r/', '', $content);
		$content = strip_tags($content);
		$content = htmlspecialchars($content);
		$content = explode(".",$content);
		
		shuffle($content);
		
		$content = implode(".", $content);
		$textwords = explode(" ", $content);
		$loopmax = count($textwords) - ($gran - 2) - 1;
		
		for ($j = 0; $j < $loopmax; $j++) {
			$key_string = " ";
			$end = $j + $gran;
			
			for ($k = $j; $k < $end; $k++) {
				$key_string .= $textwords[$k].' ';
			}
			
			$frequency_table[$key_string] = ' ';
			$frequency_table[$key_string] .= $textwords[$j + $gran]." ";
			
			if (($j+$gran) > $loopmax ) {
				break;
			}
		}
		
		for ($i = 0; $i < $gran; $i++) {
			$lastwords[] = $textwords[$i];
			$buffer .= " ".$textwords[$i];
		}
		
		for ($i = 0; $i < $num; $i++) {
			$key_string = " ";
			for ($j = 0; $j < $gran; $j++) {
				$key_string .= $lastwords[$j]." ";
			}
			if (isset($frequency_table[$key_string])) {
				$possible = explode(" ", trim($frequency_table[$key_string]));
				mt_srand();
				$c = count($possible);
				$r = mt_rand(1, $c) - 1;
				$nextword = $possible[$r];
				$buffer .= " $nextword";
				
				if (strlen($buffer) >= $letters_line) {
					$output .= $buffer;
					$buffer = " ";
				}
		  
				for ($l = 0; $l < $gran - 1; $l++) {
					$lastwords[$l] = $lastwords[$l + 1];
				}
				
				$lastwords[$gran - 1] = $nextword;
			} else {
				$lastwords = array_splice($lastwords, 0, count($lastwords));
				
				for ($l = 0; $l < $gran; $l++) {
					$lastwords[] = $textwords[$l];
					$buffer .= ' '.$textwords[$l];
				}
			}
		}
	  return trim($output);
	}
}

?>
