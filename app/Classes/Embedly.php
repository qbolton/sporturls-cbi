<?php
class Embedly {
  static public function extract($url) {
    $user_agent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/38.0.2125.111 Safari/537.36";

    //print_r($url);

    // instanciate 
    $api = new Embedly\Embedly(
      array('user_agent' => $user_agent, 'key' => 'ae3d2e9ea20d4fb194f645d62a8de8c5')
    );

    // make the extract call
    $result = $api->extract($url);

    // send the api result back
    return $result;
  }
}
