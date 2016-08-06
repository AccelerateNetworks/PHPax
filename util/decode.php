<?php

$control = array();
$control[2] = "<STX>";
$control[3] = "<ETX>";
$control[6] = "<ACK>";
$control[28] = "<FS>";
$control[31] = "<US>";
$control[4] = "<EOT>";

$input = base64_decode(file_get_contents('php://stdin'));
$buffer = "";
foreach(str_split($input) as $letter) {
  if(ord($letter) < 48) {
    if(strlen($buffer) > 0) {
      echo $buffer."\n";
    }
    $buffer = "";
    if(in_array(ord($letter), $control)) {
      echo $control[ord($letter)]."\n";
    } else {
      echo "$letter => ".ord($letter)." (dec) ".dechex(ord($letter))." (hex)\n";
    }
  } else {
    $buffer .= $letter;
  }
}
echo $buffer."\n";
