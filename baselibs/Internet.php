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
 *   baselibs/Internet.php
 *   Hello World :)
 *
 *   @author Neek
 *   @todo Implement sockets ;)
 */
class Internet {

    /**
     * Grab some file from the internets
     *
     * @param string $url
     * @return string $data
     */
    public static function Grab($url) {
        $data = '';
        $url = str_replace(" ","+",$url);
        $data = Internet::GrabSimple($url);
        if(!empty($data)) {
            return $data;
        }
        $data = Internet::GrabCurl($url);
        if(!empty($data)) {
            return $data;
        }
        $data = Internet::GrabSockets($url);
        return $data;
    }

    private static function GrabCurl($url) {
        if(!function_exists('curl_init')) {
            return;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $cdata = curl_exec($ch);
        curl_close($ch);
        return $cdata;
    }

    private static function GrabSockets($url) {
        if(!function_exists('fsockopen')) {
			return;
		}
        $fp = fsockopen($url, 80, $errno, $errstr, 30);
		if (!$fp) {
			//echo "$errstr ($errno)<br />\n";
			return '';
		} else {
			$out  = "GET / HTTP/1.1\r\n";
			$out .= "Host: ".$url."\r\n";
			$out .= "Connection: Close\r\n\r\n";
			fwrite($fp, $out);
			while (!feof($fp)) {
				$output = $output.fgets($fp, 1024);
			}
			fclose($fp);
			return $output;
		}
    }

    private static function GrabSimple($url) {
        return file_get_contents($url);
    }
}
?>
