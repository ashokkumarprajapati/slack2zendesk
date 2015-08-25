<?php
$messages = json_decode($HTTP_RAW_POST_DATA);

$zd_subdomain = getenv('ZENDESK_SUBDOMAIN');
$zd_username = getenv('ZENDESK_USERNAME');
$zd_api_token = getenv('ZENDESK_API_TOKEN');
$debug = getenv('DEBUG_ENABLED');
$slack_token = "cd9PEhQSUzJzYVjHPAYmLNSN";
error_log("normal name".$messages->channel_name);
error_log("Post data".$_POST["channel_name"]);
if ($messages) {
  $channel_name = $messages->channel_name;
  $user_id = $messages->user_id;
  $requester_name = $messages->user_name;
  $text = $messages->text;
  $token = $messages->token;
  $trigger_type = $messages->trigger_word;
  if ($debug == "true"){
     error_log("message recieved from slack: channel=".$channel_name ."username=".$requester_name." message=".$text);
  }
  if ($slack_token != $token){
     error_log("This invalid request as it doesn't have correct token.");
     return 200;
  }
  switch ($trigger_type) {
    case "@change":
      $verb = "triggered";
      //Remove the pd_integration tag in Zendesk to eliminate further updates
      $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets.json";
      $title = strstr($text,"@change");
      //$data = array('ticket' => array( 
 		  //             'group\_id' => 24712511,    
 		  //             'subject' => "Change :".$text,  
 		  //            'comment' => $text . "\n\n Created on behalf of:".$requester_name) 
 		  //        );
      $data_json = json_encode($data);
      $status_code = http_request($url, $data_json, "POST", "basic", $zd_username, $zd_api_token);
      break;
    case "@approved":
      $verb = "acknowledged ";
      $ticket_id = strstr($text,"@approved ");
      $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets/$ticket_id.json";
      $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"Approved by $requester_name")));
      $data_json = json_encode($data);
      $status_code = http_request($url, $data_json, "PUT", "basic", $zd_username, $zd_api_token);
      if ($status_code != "200") {
          //If we did not POST correctly to Zendesk, we'll add a note to the ticket, as long as it was a triggered or acknowledged ticket.
      	  error_log("Error while trying to approve ticket in zendesk");
	}      
      break;
    case "@ticket":
      $verb = "resolved";
      break;
    default:
      continue 2;
  }
}
function http_request($url, $data_json, $method, $auth_type, $username, $token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  if ($auth_type == "token") {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json),"Authorization: Token token=$token"));
    curl_setopt($ch, CURLOPT_HTTPAUTH);
  }
  else if ($auth_type == "basic") {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username/token:$token");
  }
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //curl_setopt($ch, CURLOPT_VERBOSE, true);
  $response  = curl_exec($ch);
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $status_code;
}
?>
