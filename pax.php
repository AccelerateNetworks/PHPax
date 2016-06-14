<?php
class Pax {

  const PROTO_VERSION = "1.28";
  public $host = '127.0.0.1';
  public $port = 10009;

  function __construct($host, $port=10009) {
    $this->host = $host;
    $this->port = $port;
  }

  function lrc($input) {
    error_log("Doing LRC on ".$input);
    $out = 0;
    foreach(str_split($input) as $c) {
      $out ^= ord($c);
    }
    return chr($out);
  }

  public function build_request($command, $args=array(), $debug=false) {
    $args_str = "";
    if(count($args) > 0) {
      $args_str = implode(chr(28), $args).chr(28);
    }
    $cmd = $command.chr(28).self::PROTO_VERSION.chr(28).$args_str.chr(3);
    $cmd = chr(2).$cmd.self::lrc($cmd);
    return base64_encode($cmd);
  }

  function http_request($query) {
    # TODO: Allow certificate pinning, use certificates at all, etc
    error_log("WARNING! Instead of verifying the remote certificate any of that 'encryption' shit, we're just doing it in the clear.");
    return file_get_contents("http://".$this->host.":".$this->port."/?".$query);
  }

  function parse_response($response) {
    $lrc = explode(chr(3), trim($response, chr(2)));
    $expected = self::lrc($lrc[0]);
    if($lrc[1] != $expected) {
      // throw new Exception('LRC Mismatch! Got '.$lrc[1].' but expected '.$expected);
      error_log('LRC Mismatch! Got '.$lrc[1].' but expected '.$expected);
    }
    $fields = explode(chr(28), $lrc[0]);
    return $fields;
  }

  public function make_call($command, $args=array(), $debug=false) {
    $query = $this->build_request($command, $args, $debug);
    $result = $this->http_request($query);
    // TODO: Decode $result
    return $this->parse_response($result);
  }

}
