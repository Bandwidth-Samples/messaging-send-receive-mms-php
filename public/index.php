<?php

use BandwidthLib\Messaging\Models\BandwidthCallbackMessage;
use BandwidthLib\Messaging\Models\BandwidthMessage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$BW_ACCOUNT_ID = getenv("BW_ACCOUNT_ID");
$BW_USERNAME = getenv("BW_USERNAME");
$BW_PASSWORD = getenv("BW_PASSWORD");
$BW_NUMBER = getenv("BW_NUMBER");
$BW_MESSAGING_APPLICATION_ID = getenv("BW_MESSAGING_APPLICATION_ID");

$config = new BandwidthLib\Configuration(
    array(
        "messagingBasicAuthUserName" => $BW_USERNAME,
        "messagingBasicAuthPassword" => $BW_PASSWORD
        //"environment" => BandwidthLib\Environments::CUSTOM,
        //"baseUrl" => 'https://custom-url.com'
        // These two lines are used for setting a custom URL to send the request to
        // Documentation can be found [here](https://dev.bandwidth.com/sdks/php.html)
    )
);
$client = new BandwidthLib\BandwidthClient($config);

// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

$messagingClient = $client->getMessaging()->getClient();

$app->post('/callbacks/outbound/messaging', function (Request $request, Response $response) {
  global $messagingClient, $BW_ACCOUNT_ID, $BW_MESSAGING_APPLICATION_ID, $BW_NUMBER;

  $data = $request->getParsedBody();
  $body = new BandwidthLib\Messaging\Models\MessageRequest();
  $body->from = $BW_NUMBER;
  $body->to = array($data['to']);
  $body->applicationId = $BW_MESSAGING_APPLICATION_ID;
  $body->text = $data['text'];
  $body->media = array("https://cdn2.thecatapi.com/images/MTY3ODIyMQ.jpg");
  try {
      $msgResponse = $messagingClient->createMessage($BW_ACCOUNT_ID, $body);
      $response->getBody()->write('{"Success":"Message sent successfully"}');
      return $response->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
      $response->getBody()->write('{"Error":"Message Failed"}'.$e);
      return $response->withStatus(400)
        ->withHeader('Content-Type', 'application/json');
  }
});

$app->post('/callbacks/outbound/messaging/status', function (Request $request, Response $response) {
  
  $data = $request->getBody()->getContents();
  $messagingCallbacks = \BandwidthLib\APIHelper::deserialize($data, BandwidthCallbackMessage::class, true );
  $messageCallback = array_pop($messagingCallbacks);
  $mediaName = $messageCallback->message->media;

  $type = $messageCallback->type;
  $time = $messageCallback->time;
  $description = $messageCallback->description;
  $to = $messageCallback->to;
  $message = $messageCallback->message;    // an object
  $messageId = $messageCallback->message->id;
  $messageTime = $messageCallback->message->time;
  $messageTo = $messageCallback->message->to;    // an array
  $messageFrom = $messageCallback->message->from;
  $messageText = $messageCallback->message->text;
  $messageApplicationId = $messageCallback->message->applicationId;
  if (isset($messageCallback->message->media)) {
    $messageMedia = $messageCallback->message->media;    // an array
  } else {
    $messageMedia = [];
  }
  $messageOwner = $messageCallback->message->owner;
  $messageDirection = $messageCallback->message->direction;
  $messageSegmentCount = $messageCallback->message->segmentCount;

  if ($messageDirection == "out"){
    $myfile = fopen("outbound_message.txt", "w") or die("Unable to open file!");
    $txt = "Type: ".$type."\nDescription: ".$description."\nText: ".$messageText;
    fwrite($myfile, $txt);
    fclose($myfile);
  } else {
    $myfile = fopen("outbound_status.txt", "w") or die("Unable to open file!");
    $txt = "Message type does not match endpoint. This endpoint is used for message status callbacks only.";
    fwrite($myfile, $txt);
    fclose($myfile);
  }

  return $response->withStatus(200);
});

$app->post('/callbacks/inbound/messaging', function (Request $request, Response $response) {
  global $messagingClient, $BW_ACCOUNT_ID;

  $data = $request->getBody()->getContents();
  $messagingCallbacks = \BandwidthLib\APIHelper::deserialize($data, BandwidthCallbackMessage::class, true );
  $messageCallback = array_pop($messagingCallbacks);
  $mediaName = $messageCallback->message->media;

  $type = $messageCallback->type;
  $time = $messageCallback->time;
  $description = $messageCallback->description;
  $to = $messageCallback->to;
  $message = $messageCallback->message;    // an object
  $messageId = $messageCallback->message->id;
  $messageTime = $messageCallback->message->time;
  $messageTo = $messageCallback->message->to;    // an array
  $messageFrom = $messageCallback->message->from;
  $messageText = $messageCallback->message->text;
  $messageApplicationId = $messageCallback->message->applicationId;
  if (isset($messageCallback->message->media)) {
    $messageMedia = $messageCallback->message->media;    // an array
  } else {
    $messageMedia = [];
  }
  $messageOwner = $messageCallback->message->owner;
  $messageDirection = $messageCallback->message->direction;
  $messageSegmentCount = $messageCallback->message->segmentCount;


  if ($messageDirection == "in"){
    $myfile = fopen("inbound_message.txt", "w") or die("Unable to open file!");
    $txt = "Type: ".$type."\nDescription: ".$description."\nText: ".$messageText."\nMedia Array: ".implode(", ", $messageMedia);
    fwrite($myfile, $txt);
    fclose($myfile);

    // download each file in the media array
    for ($i = 0; $i < count($messageMedia); $i++){
      $mediaId = substr($messageMedia[$i], strpos($messageMedia[$i], "media/") + 6);
      $ext = substr($mediaId, strpos($mediaId, "."));
      $mediaResponse = $messagingClient->getMedia($BW_ACCOUNT_ID, $mediaId);
      $file = fopen("media_file".$i.$ext, "wb") or die("Unable to open file");
      fwrite($file, $mediaResponse->getResult());
      fclose($file);
    }
  } else {
    $myfile = fopen("inbound_message.txt", "w") or die("Unable to open file!");
    $txt = "Message type does not match endpoint. This endpoint is used for inbound messages only.\nOutbound message callbacks should be sent to /callbacks/outbound/messaging.";
    fwrite($myfile, $txt);
    fclose($myfile);
  }

  return $response->withStatus(200);
});

$app->run();
