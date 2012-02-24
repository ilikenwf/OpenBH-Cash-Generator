<?php
class TheBestSpinner implements HookBase
{

    function EnrichContent($content,$keyword,$args)
	{
            return $this->spintext($content,$args['user'],$args['pass']);
	}
//spin the article
    function spintext($target,$user,$pass) {
	$url = 'http://thebestspinner.com/api.php';
	$testmethod = 'replaceEveryonesFavorites';
	$data = array();
	$data['action'] = 'authenticate';
	$data['format'] = 'php'; # You can also specify 'xml' as the format.

	# The user credentials should change for each UAW user with a TBS account.

	$data['username'] = $user;
	$data['password'] = $pass;

	# Authenticate and get back the session id.
	# You only need to authenticate once per session.
	# A session is good for 24 hours.
	$output = unserialize($this->curl_post($url, $data, $info));

	if($output['success']=='true'){
	  # Success.
	  $session = $output['session'];

	  # Build the data array for the example.
	  $data = array();
	  $data['session'] = $session;
	  $data['format'] = 'php'; # You can also specify 'xml' as the format.
	  $data['text'] = $target;
	  $data['action'] = $testmethod;
	  $data['maxsyns'] = '5'; # The number of synonyms per term.

	  if($testmethod=='replaceEveryonesFavorites'){
	    # Add a quality score for this method.
	    $data['quality'] = '1';
	  }
	  # Post to API and get back results.
	  $output = $this->curl_post($url, $data, $info);
	  $output = unserialize($output);
	  $spunText = $output['output'];

	  $data = array();
	  $data['session'] = $session;
	  $data['format'] = 'php'; # You can also specify 'xml' as the format.
	  $data['text'] = $spunText;
	  $data['action'] = "randomSpin";
	    $output = $this->curl_post($url, $data, $info);
	    $output = unserialize($output);
	    $output = $output['output'];

    }

    return $output;
    }

    function curl_post($url, $data, &$info){

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->curl_postData($data));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_REFERER, $url);
      $html = trim(curl_exec($ch));
      curl_close($ch);

      return $html;
    }

    function curl_postData($data){

      $fdata = "";
      foreach($data as $key => $val){
	$fdata .= "$key=" . urlencode($val) . "&";
      }

      return $fdata;

    }
}
?>
