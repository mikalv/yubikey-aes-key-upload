<?php

/* Written by Simon Josefsson <simon@josefsson.org>.
 * Copyright (c) 2009, 2010, 2011 Yubico AB
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above
 *     copyright notice, this list of conditions and the following
 *     disclaimer in the documentation and/or other materials provided
 *     with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

function hex2bin($h)
{
  if (!is_string($h)) return null;
  $r='';
  for ($a=0; $a<strlen($h); $a+=2) {
    $r.=chr(hexdec($h{$a}.$h{($a+1)}));
  }
  return $r;
}

function modhex2hex($m)
{
  return strtr ($m, "cbdefghijklnrtuv", "0123456789abcdef");
}

function aes128ecb_decrypt($key,$in)
{
  return bin2hex(mcrypt_ecb(MCRYPT_RIJNDAEL_128,
			    hex2bin($key),
			    hex2bin($in),
			    MCRYPT_DECRYPT,
			    hex2bin('00000000000000000000000000000000')));
}
	
function calculate_crc($token)
{
  $crc = 0xffff;

  for ($i = 0; $i < 16; $i++ ) {
    $b = hexdec($token[$i*2].$token[($i*2)+1]);
    $crc = $crc ^ ($b & 0xff);
    for ($j = 0; $j < 8; $j++) {
      $n = $crc & 1;
      $crc = $crc >> 1;
      if ($n != 0) {
        $crc = $crc ^ 0x8408;
      }
    }
  }
  return $crc;
}

function crc_is_good($token) {
  $crc = calculate_crc($token);
  return $crc == 0xf0b8;
}

function debug() {
  $str = "";
  foreach (func_get_args() as $msg)
    {
      if (is_array($msg)) {
	foreach($msg as $key => $value){
	  $str .= "$key=$value ";
	}
      } else {
	$str .= $msg . " ";
      }
    }
  error_log($str);
}

// This function takes a list of URLs.  It will return the content of
// the first successfully retrieved URL, whose content matches ^OK.
// The request are sent asynchronously.  Some of the URLs can fail
// with unknown host, connection errors, or network timeout, but as
// long as one of the URLs given work, data will be returned.  If all
// URLs fail, data from some URL that did not match ^OK is returned,
// or if all URLs failed, false.
function retrieveURLasync ($urls) {
  $mh = curl_multi_init();

  $ch = array();
  foreach ($urls as $id => $url) {
    $handle = curl_init();

    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_USERAGENT, "YK-AKU");
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($handle, CURLOPT_FAILONERROR, true);
    curl_setopt($handle, CURLOPT_TIMEOUT, 10);

    curl_multi_add_handle($mh, $handle);

    $ch[$handle] = $handle;
  }

  $str = false;

  do {
    while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM)
      ;

    while ($info = curl_multi_info_read($mh)) {
      debug ("YK-KSM multi", $info);
      if ($info['result'] == CURLE_OK) {
	$str = curl_multi_getcontent($info['handle']);
	
	if (preg_match("/^OK/", $str)) {
	  $error = curl_error ($info['handle']);
	  $errno = curl_errno ($info['handle']);
	  $info = curl_getinfo ($info['handle']);
	  debug("YK-KSM errno/error: " . $errno . "/" . $error, $info);

	  foreach ($ch as $h) {
	    curl_multi_remove_handle ($mh, $h);
	    curl_close ($h);
	  }
	  curl_multi_close ($mh);

	  return $str;
	}

	curl_multi_remove_handle ($mh, $info['handle']);
	curl_close ($info['handle']);
	unset ($ch[$info['handle']]);
      }

      curl_multi_select ($mh);
    }
  } while($active);

  foreach ($ch as $h) {
    curl_multi_remove_handle ($mh, $h);
    curl_close ($h);
  }
  curl_multi_close ($mh);

  return $str;
}

?>
