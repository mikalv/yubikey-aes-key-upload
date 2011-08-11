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

require_once('recaptchalib.php');
require_once('ykaku-tools.php');
require_once('ykaku-config.php');

$resp = null;
$error = null;
$ok = false;
$email_err = "";
$serial_err = "";
$prefix_err = "";
$uid_err = "";
$aeskey_err = "";
$otp_err = "";

function yubikeyPrefixExist_p ($otp) {
  $urls = otp2ksmurls ($otp, 0);
  if (!is_array($urls)) {
    return false;
  }
  $response = retrieveURLasync ($urls);
  if ($response) {
    debug("YK-KSM response: " . $response);
  }

  return !(strstr ($response, "ERR Unknown yubikey")
	|| strstr ($response, "ERR Unknown public_id"));
} // End decryptOTP

if ($_REQUEST["posted"]) {
  $resp = recaptcha_check_answer ($privatekey,
				  $_SERVER["REMOTE_ADDR"],
				  $_REQUEST["recaptcha_challenge_field"],
				  $_REQUEST["recaptcha_response_field"]);
  if (!$resp->is_valid) {
    $error = $resp->error;
  }
  if (!preg_match("/^.*@.*\..*$/", $_REQUEST["email"])) {
    $email_err = "Invalid e-mail address";
  }
  if (!preg_match("/^[0-9]+$/", $_REQUEST["serial"])) {
    $serial_err = "Serial number must be an integer - use 0 if you don't have one";
  }
  if (strlen ($_REQUEST["serial"]) > 9) {
    $serial_err = "YubiKey serial number too large";
  }
  if (strlen ($_REQUEST["prefix"]) != 12) {
    $prefix_err = "YubiKey prefix must be 12 characters long";
  }
  else if (substr ($_REQUEST["prefix"], 0, 2) != "vv") {
    $prefix_err = "YubiKey prefix must begin with 'vv'";
  }
  else if (!preg_match("/^[cbdefghijklnrtuv]{12}$/", $_REQUEST["prefix"])) {
    $prefix_err = "YubiKey prefix must consist only of modhex characters (cbdefghijklnrtuv)";
  }
  if (strlen ($_REQUEST["uid"]) != 12) {
    $uid_err = "Identity must be 12 characters long";
  }
  else if (!preg_match("/^[0-9A-Fa-f]{12}$/", $_REQUEST["uid"])) {
    $uid_err = "Identity must consist only of hex characters (0-9A-F)";
  }
  if (strlen ($_REQUEST["aeskey"]) != 32) {
    $aeskey_err = "AES key must be 32 character long";
  }
  else if (!preg_match("/^[0-9A-Fa-f]{32}$/", $_REQUEST["aeskey"])) {
    $aeskey_err = "AES key must consist only of hex characters (0-9A-F)";
  }
  if (strncmp ($_REQUEST["otp"], $_REQUEST["prefix"], 12) != 0) {
    $otp_err = "OTP prefix mismatch";
  } else if (!preg_match("/^([cbdefghijklnrtuv]{0,16})([cbdefghijklnrtuv]{32})$/",
		  $_REQUEST["otp"], $matches)) {
    $otp_err = "Invalid YubiKey OTP";
  } else {
    $id = $matches[1];
    $modhex_ciphertext = $matches[2];
    $ciphertext = modhex2hex($modhex_ciphertext);
    $plaintext = aes128ecb_decrypt($_REQUEST["aeskey"], $ciphertext);
    $uid = substr($plaintext, 0, 12);
    if (!crc_is_good($plaintext)) {
      $otp_err = "Corrupt YubiKey OTP";
    } else if (strcmp($uid, $_REQUEST["uid"]) != 0) {
      $uid_err = "Identity in OTP does not match";
    }    
  }
  if (($prefix_err . $otp_err . $aeskey_err . $uid_err) == "") {
    if (yubikeyPrefixExist_p ($_REQUEST["otp"])) {
      $prefix_err = "Sorry, that yubikey prefix is already in use";
    }
  }
  if ($resp && $resp->is_valid
   && ($email_err . $serial_err . $prefix_err . $uid_err . $aeskey_err . $otp_err) == "") {
    $ok = true;
  }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en"	xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
  <link rel="stylesheet" type="text/css" href="style.css" />
  <title>Yubico AES Key Upload</title>
</head>

<body>
<div id="stripe">&nbsp;</div>	
<div id="container">
  <div id="logoArea">
    <img src="images/yubicoLogo.gif" alt="yubicoLogo" width="150" height="75"/>
  </div>

  <div id="greenBarContent">
    <div id="greenBarImage">
      <img src="images/yubikey.jpg" alt="yubikey" width="150" height="89"/>
    </div>
    <div id="greenBarText">
      <h3>Yubico AES Key Upload</h3>
    </div>
  </div>

  <div id="bottomContent">
<?php if ($ok) { ?>
<p><pre>
<?php
    $now = gmstrftime ("%Y-%m-%dT%H:%M:%S");
    $file = "/var/spool/ykaku/keys-$now.asc";
    $h = popen("gpg --batch --homedir /etc/ykaku --default-key $defaultkey --armor --encrypt --sign $recipients > $file 2>&1", "w");
    $str = sprintf ("# ykksm 1\n# email: %s\n# otp: %s\n%s,%s,%s,%s,000000000000,%s,\n",
		   $_REQUEST["email"], $_REQUEST["otp"],
		   $_REQUEST["serial"], $_REQUEST["prefix"],
		   $_REQUEST["uid"], $_REQUEST["aeskey"],
		   $now);
    fwrite ($h, $str);
    pclose ($h);
?>
</pre>
    <p><b>Success!</b></p>

    <p>Key upload successful.</p>

    <p><table border=1>
    <tr><td>E-mail address:</td><td><?php echo $_REQUEST["email"]; ?></td></tr>
    <tr><td>Serial number:</td><td><?php echo $_REQUEST["serial"]; ?></td></tr>
    <tr><td>Yubikey prefix:</td><td><?php echo $_REQUEST["prefix"]; ?></td></tr>
    <tr><td>Internal identity:</td><td><?php echo $_REQUEST["uid"]; ?></td></tr>
    <tr><td>AES key:</td><td><?php echo $_REQUEST["aeskey"]; ?></td></tr>
    <tr><td>YubiKey OTP:</td><td><?php echo $_REQUEST["otp"]; ?></td></tr>
    </table>
    </p>

    <p>Try our <a href="http://www.yubico.com/1">online test
       service</a> to verify that your newly programmed YubiKey is
       working against our validation server.</p>

<?php } else { ?>
    <p>Please enter information about your newly personalized YubiKey.</p>

    <p><b>Please note: It takes 15 minutes for an uploaded identity to
    become valid on our validation serviers.  Please wait 15-20
    minutes before testing an uploaded identity.</b></p>

    <form name="upload" method="post">
    <input type=hidden name="posted" value="ok">

    <table>
    <tr>
      <td>Your e-mail address:</td>
      <td><input type=text length=13 name="email"
                 value="<?php echo $_REQUEST["email"]; ?>"></td>
      <td><font color="red"><?php print $email_err; ?>
    </tr>
    <tr>
      <td>Serial number:</td>
      <td><input type=text size=10 name="serial" value="<?php echo $_REQUEST["serial"]; ?>"></td>
      <td><font color="red"><?php print $serial_err; ?></tr>
    </tr>
    <tr>
      <td>YubiKey prefix:</td>
      <td><input type=text length=13 name="prefix"
                 value="<?php if ($_REQUEST["prefix"]) { echo $_REQUEST["prefix"]; } else { echo "vv"; } ?>"></td>
      <td><font color="red"><?php print $prefix_err; ?>
    </tr>
    <tr>
      <td>Internal identity:</td>
      <td><input type=text size=13 name="uid" value="<?php echo $_REQUEST["uid"]; ?>"></td>
      <td><font color="red"><?php print $uid_err; ?></tr>
    </tr>
    <tr>
      <td>AES Key:</td>
      <td><input type=text length=33 name="aeskey" value="<?php echo $_REQUEST["aeskey"]; ?>"></td>
      <td><font color="red"><?php print $aeskey_err; ?></tr>
    </tr>
    <tr>
      <td>OTP from the YubiKey:</td>
      <td><input type=text name="otp" value="<?php echo $_REQUEST["otp"]; ?>"></td>
      <td><font color="red"><?php print $otp_err; ?></tr>
    </tr>
    <tr><td colspan=3><?php echo recaptcha_get_html($publickey, $error, true); ?></td></tr>
    <tr><td colspan=3 align=center><input type=submit value="Upload AES key"></td></tr>
    </table>

    </form>
  </div>
<?php } ?>
</div>
</body>
</html>
