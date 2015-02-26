<?php
/**
 * Converts the open status of the space api into an rss feed.
 */

function getGUID($str){
  $sha = sha1($str);
  $charid = strtoupper($sha);
  $hyphen = chr(45);// "-"
  $uuid = substr($charid, 0, 8).$hyphen
         .substr($charid, 8, 4).$hyphen
         .substr($charid,12, 4).$hyphen
         .substr($charid,16, 4).$hyphen
         .substr($charid,20,12);
  return $uuid;  
}

require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/config.php';

$force_update = false;

if (count($argv) == 2) {
    if ($argv[1] == '--force') {
        $force_update = true;
    }
}

$client = new GuzzleHttp\Client();
$client->setDefaultOption('verify', false);
$res = $client->get($conf['spaceapi']);

if ($res->getStatusCode() == 200) {
    $spaceapi = $res->getBody();
    $spaceapi = json_decode($spaceapi);
    if ($spaceapi instanceof stdClass) {
        $rss = new UniversalFeedCreator();
        $rss->title = 'Raumstatus für ' . $spaceapi->space;
        $rss->description = 'Zeigt an ob der Raum geöffnet oder geschlossen ist.';
        $rss->id = sha1($rss->title);

        $image = new FeedImage();
        $image->title = $spaceapi->space . " logo";
        $image->url = $spaceapi->logo;
        $image->link = $spaceapi->url;
        $image->description = "";

        $rss->image = $image;

        $rss->descriptionTruncSize = 500;
        $rss->descriptionHtmlSyndicated = false;

        $rss->link = $spaceapi->url;
        $rss->syndicationURL = $conf['url'];
        $history = [];
        if (file_exists(__DIR__ . '/hist_data')) {
            $history = unserialize(file_get_contents(__DIR__ . '/hist_data'));
        }
        if (count($history) > 0) {
            if ($history[0]['open'] == $spaceapi->state->open) {
                if (!$force_update)
                    die("Nothing changed, nothing to do.\n");
            } else {
                $element = [
                    'date' => $spaceapi->state->lastchange,
                    'open' => $spaceapi->state->open,
                ];
                $history = array_merge([$element], $history);
                if (count($history) > 20) {
                    array_pop($history);
                }
            }
        } else {
            $element = [
                'date' => $spaceapi->state->lastchange,
                'open' => $spaceapi->state->open,
            ];
            $history[] = $element;
        }

        foreach ($history as $item) {
            $fi = new FeedImage();
            if ($item['open']) {
                $fi->title = $spaceapi->space . ' ist seit ' . date('G:i',$item['date']) . ' Uhr geöffnet';
            } else {
                $fi->title = $spaceapi->space . ' ist seit ' . date('G:i',$item['date']) . ' Uhr geschlossen';
            }
            $fi->guid = 'urn:uuid:'. getGUID($item['date']);
            $fi->date = date('c', $item['date']);
            $fi->link = $spaceapi->url;
            $fi->author = 'spaceapi2rss';
            $rss->addItem($fi);
        }

        $rss->saveFeed('ATOM', $conf['filepath'], false);
        file_put_contents(__DIR__ . '/hist_data', serialize($history));
        echo "Feed successfully generated\n";
    }
} else {
    echo "Could not fetch the SpaceAPI\n";
}