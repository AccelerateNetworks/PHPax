<?php
require "pax.php";

$terminal = new Pax('192.168.2.213');

### Inform customers about our dank memes
print_r($terminal->make_call('A24', array('Check out our selection of', '', 'Dank Memes!', '30')));

### Do credit charge for $1.00
$args = array();
$args[] = '01'; # Transaction type. 01 = Sale/Redeem
$args[] = '100'; # Presumably the amount
$args[] = '';
$args[] = '1'; # No clue yet
$args[] = '';
$args[] = '';
$args[] = '';
$args[] = '';
print_r($terminal->make_call('T00', $args));
