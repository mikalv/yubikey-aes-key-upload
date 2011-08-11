<?php

// otp2ksmurls: Return array of YK-KSM URLs for decrypting OTP for
// CLIENT.  The URLs must be fully qualified, i.e., contain the OTP
// itself.
function otp2ksmurls ($otp, $client) {
  return array(
	       "http://ykkms1.example.com/wsapi/decrypt?otp=$otp",
	       "http://ykkms2.example.com/wsapi/decrypt?otp=$otp",
	       );
}

// Get a key from http://recaptcha.net/api/getkey
$publickey = "publickey";
$privatekey = "privatekey";

// AES Key Upload encrypt settings
$defaultkey = "12345678";
$recipients = "--recipient 23456789 --recipient 45678901 --recipient 67890123";

?>
