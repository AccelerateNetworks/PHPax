<?php
foreach(str_split(file_get_contents('php://stdin')) as $letter) {
  echo "$letter => ".ord($letter)." (dec) ".dechex(ord($letter))." (hex)\n";
}
