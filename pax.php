<?php
/*******************************************************************************

    Copyright 2016 Accelerate Networks

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include_once(dirname(__FILE__).'/../../../lib/AutoLoader.php');
use Guzzle\Http\Client;


class Pax {
  const PROTO_VERSION = "1.28";
  public $host = '127.0.0.1';
  public $port = 10009;

  function __construct($host, $port=10009) {
    $this->host = $host;
    $this->port = $port;
  }

  private function lrc($input) {
    $out = 0;
    foreach(str_split($input) as $c) {
      $out ^= ord($c);
    }
    return chr($out);
  }

  public function build_request($command, $args=array(), $debug=false) {
    $args_str = "";
    $processed_args = array();
    foreach($args as $arg) {
      if(is_array($arg)) {
        $processed_args[] = implode(chr(31), $arg);
      } else {
        $processed_args[] = $arg;
      }
    }
    if(count($args) > 0) {
      $args_str = implode(chr(28), $processed_args).chr(28);
    }
    $cmd = $command.chr(28).self::PROTO_VERSION.chr(28).$args_str.chr(3);
    $cmd = chr(2).$cmd.self::lrc($cmd);
    return base64_encode($cmd);
  }

  private function http_request($query) {
    # TODO: Allow certificate pinning, use certificates at all, etc
    error_log("WARNING! Instead of verifying the remote certificate any of that 'encryption' shit, we're just doing it in the clear.");
    $client = new Client("http://".$this->host.":".$this->port);
    $request = $client->get("/?".$query);
    $query = $request->getQuery();
    $query->useUrlEncoding(false);
    $response = $request->send();
    return $response->getBody();
  }

  private function parse_response($response) {
    $lrc = explode(chr(3), trim($response, chr(2)));
    $expected = self::lrc(substr($response, 1, -1));
    if($lrc[1] != $expected) {
      throw new Exception('LRC Mismatch! Got '.$lrc[1].' but expected '.$expected);
    }
    $fields = explode(chr(28), $lrc[0]);
    $out = array();
    $out['command'] = $fields[1];
    $out['version'] = intval($fields[2]);
    $out['code'] = intval($fields[3]);
    $out['fields'] = $fields;
    return $out;
  }

  private function parse_transaction($terminal_response) {
    $out = $terminal_response;
    $out['message'] = $out['fields'][4];
    if($out['code'] == 0) {
      // Non-error

      // Host field
      $host = explode(chr(31), $out['fields'][5]);
      $out['host']['respones']['code'] = $host[0];
      $out['host']['response']['message'] = $host[1];
      $out['host']['auth'] = $host[2];
      $out['host']['reference_number'] = $host[3];
      $out['host']['trace'] = $host[4];
      $out['host']['batch'] = $host[5];

      $out['transaction_type'] = $out['fields'][6];

      $amount = explode(chr(31), $out['fields'][7]);
      $out['amount']['approved'] = floatval($amount[0])/100;
      $out['amount']['due'] = $amount[1];
      $out['amount']['tip'] = $amount[2];
      $out['amount']['cash_back'] = $amount[3];
      $out['amount']['fee'] = $amount[4];
      $out['amount']['tax'] = $amount[5];
      $out['amount']['balance'] = array($amount[6], $amount[7]);

      $account = explode(chr(31), $out['fields'][8]);
      $out['account']['number'] = $account[0];
      $out['account']['entry'] = $account[1];
      $out['account']['expiry'] = $account[2];
      $out['account']['ebt_type'] = $account[3];
      $out['account']['voucher'] = $account[4];
      $out['account']['new_account_number'] = $account[5];
      $out['account']['type'] = $account[6];
      $out['account']['name'] = $account[7];
      $out['account']['cvd']['approva_code'] = $account[8];
      $out['account']['cvd']['message'] = $account[9];
      $out['account']['card_present'] = $account[10] == "0";

      $trace = explode(chr(31), $out['fields'][9]);
      $out['trace']['transaction'] = $trace[0];
      $out['trace']['reference'] = $trace[1];
      $out['trace']['timestamp'] = $trace[2];

      if(count($out['fields']) == 14) {
        $out['avs'] = explode(chr(31), $out['fields'][10]);
        $out['commercial'] = explode(chr(30), $out['fields'][11]); # Does not exist in debit transactions
        $out['moto'] = explode(chr(31), $out['fields'][12]); # Does not exist in debit transactions
      }
      $out['additional'] = array();
      foreach(explode(chr(31), $out['fields'][count($out['fields'])-1]) as $value) {
        $keyvalue = explode("=", $value);
        $out['additional'][$keyvalue[0]] = $keyvalue[1];
      }
    }
    return $out;
  }

  public function make_call($command, $args=array(), $debug=false) {
    $query = $this->build_request($command, $args, $debug);
    $result = $this->http_request($query);
    return $this->parse_response($result);
  }

  public function save_signature($raw) {
    $points = explode("^", $raw);
    $image = imagecreate(168, 85);
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagecolortransparent($image, $white);
    imagefill($image, 0, 0, $white);
    $lastx = 0;
    $lasty = 65535;
    $lines = array();
    $line = array();
    foreach($points as $point) {
      if($point != "~") {
        $coords = explode(",", $point);
        $x = intval($coords[0]);
        $y = intval($coords[1]);
        if(($lastx == 0 && $lasty == 65535) || ($x == 0 && $y == 65535)) {
          $lastx = $x;
          $lasty = $y;
          if(count($line) > 0) {
            $lines[] = $line;
            $line = array();
          }
        } else {
          $line[] = array($x, $y);
          imageline($image, $lastx, $lasty, $x, $y, $black);
          $lastx = $x;
          $lasty = $y;
        }
      }
    }

    if(count($line) > 0) {
      $lines[] = $line;
      $line = array();
    }

    $filename = time().".png";
    imagepng($image, __DIR__."/../signatures/".$filename);

    return array("file" => $filename, "vector" => $lines);
  }

  // Different command that can be sent to the device.
  public function do_signature($timeout=30) {
    $request = $this->make_call('A20', array(0, '', '', strval($timeout*10)));
    if($request['code'] != 0) {
      return $request;
    }
    $out = $this->make_call('A08', array(0, ''));
    $out['signature'] = self::save_signature($out['fields'][7]);
    return $out;
  }

  public function do_credit($amount) {
    $args = array('01', strval($amount*100), '', '1', '', '', '', '');
    return self::parse_transaction($this->make_call('T00', $args));
  }
  public function do_debit($amount) {
    $args = array('01', strval($amount*100), '', '1', '', '', '', '');
    return self::parse_transaction($this->make_call('T02', $args));
  }
  public function do_ebt($amount) {
    $account = array();
    $account[] = ''; # Account number
    $account[] = ''; # Expiry date
    $account[] = ''; # CVV code
    $account[] = 'C'; # EBT Type
    $args = array('01', strval($amount*100), $account, '1', '', '', '', '');
    return self::parse_transaction($this->make_call('T04', $args));
  }
  public function do_ebt_food($amount) {
    $account = array();
    $account[] = ''; # Account number
    $account[] = ''; # Expiry date
    $account[] = ''; # CVV code
    $account[] = 'F'; # EBT Type
    $args = array('01', strval($amount*100), $account, '1', '', '', '', '');
    return self::parse_transaction($this->make_call('T04', $args));
  }

  public function void_credit($reference, $transaction) {
    $trace = array($reference, '', '', $transaction);
    $args = array('16', '', '', $trace, '', '', '', '', '');
    return self::parse_transaction($this->make_call('T00', $args));
  }
}
