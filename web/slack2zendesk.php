<?php
$zd_subdomain = getenv('ZENDESK_SUBDOMAIN');
$zd_username = getenv('ZENDESK_USERNAME');
$zd_api_token = getenv('ZENDESK_API_TOKEN');
$debug = getenv('DEBUG_ENABLED');
$slack_token = "cd9PEhQSUzJzYVjHPAYmLNSN";

  $channel_name = $_POST["channel_name"];
  $user_id = $_POST["user_id"];
  $requester_name = $_POST["user_name"];
  $text = $_POST["text"];
  $token = $_POST["token"];
  $trigger_type = $_POST["trigger_word"];
  error_log("message recieved from slack: channel=".$channel_name ."username=".$requester_name." message=".$text);
  if ($debug == "true"){
     error_log("message recieved from slack: channel=".$channel_name ."username=".$requester_name." message=".$text);
  }
  if ($slack_token != $token){
     error_log("This invalid request as it doesn't have correct token.");
     return 200;
  }
  switch ($trigger_type) {
    case "@change":
      $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets.json";
      $title = explode("@change ",$text)[1];
      $data = array('ticket' => array( 
 		              'group_id' => 24712511,    
 		              'subject' => "Change :".$title,  
 		              'comment' => $text . "\n\n Created on behalf of:".$requester_name,
                   'fields' => array('27504901' => "pending_approval")
                  ) 
 		          );
      $data_json = json_encode($data);
      list($status_code,$response) = http_request($url, $data_json, "POST", "basic", $zd_username, $zd_api_token);
      $ticket_id = json_decode($response)->ticket->id;
      $slack_response = array('text' => "Zendesk ticket#.$ticket_id has been created for this change and sent for approval to CAB. \n Link : https://$zd_subdomain.zendesk.com/agent/tickets/.$ticket_id");
      echo  json_encode($slack_response);
      break;
    case "@approved":
      $content = explode("@approved ",$text);
      $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets/$content[1].json";
      $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"Approved by $requester_name"),'fields' => array('27504901' => "approved")));
      $data_json = json_encode($data);
      list($status_code,$response) = http_request($url, $data_json, "PUT", "basic", $zd_username, $zd_api_token);
      if ($status_code != "200") {
      	  //error_log("Error while trying to approve ticket in zendesk");
          $slack_response = array('text' => "Could not approve ticket in zendesk. Please check ticket number. It should be like @approved <Ticket Number>}");
          echo json_encode($slack_response);
          error_log($response);
          break;
	    } 
      $slack_response = array('text' => "Thanks for your approval. Zendesk Ticket#$content[1] has been approved and sent to DevOps for futher work.");
      echo json_encode($slack_response);
      break;
    case "@ticket":
      break;
    default:
      continue 2;
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
  //error_log($response);
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return array($status_code,$response );
}
?>
