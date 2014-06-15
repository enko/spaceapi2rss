<?php
/**
 * Converts the open status of the space api into an rss feed.
 */

/**
 * Config
 */

$conf = [
    // the url where your space api resides
    'spaceapi' => 'https://status.krautspace.de/api',
    // the url where the feed is reachable
    'url' => 'https://status.krautspace.de/rss.xml',
    // where the rss feed should be save to
    'filepath' => __DIR__ . '/rss.xml',

];


/**
 * Dont edit down here
 */

function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12)
            .chr(125);// "}"
        return $uuid;
    }
}

require_once __DIR__ . '/vendor/autoload.php';

$client = new GuzzleHttp\Client();
$client->setDefaultOption('verify', false);
$res = $client->get($conf['spaceapi']);

if ($res->getStatusCode() == 200) {
    $spaceapi = $res->getBody();
    $spaceapi = json_decode($spaceapi);
    if ($spaceapi instanceof stdClass) {
        $rss = new UniversalFeedCreator();
        $rss->title = 'room status for ' . $spaceapi->space;
        $rss->description = "";

        $image = new FeedImage();
        $image->title = $spaceapi->space . " logo";
        $image->url = $spaceapi->logo;
        $image->link = $spaceapi->url;
        $image->description = "";

        $rss->image = $image;

        $rss->descriptionTruncSize = 500;
        $rss->descriptionHtmlSyndicated = false;

        $rss->link = '';
        $rss->syndicationURL = $conf['url'];
        $history = [];
        if (file_exists(__DIR__ . '/hist_data')) {
            $history = unserialize(file_get_contents(__DIR__ . '/hist_data'));
        }
        if (count($history) > 0) {
            if ($history[0]['open'] == $spaceapi->state->open) {
                die("Nothing changed, nothing to do.\n");
            } else {
                $element = [
                    'date' => $spaceapi->state->lastchange,
                    'open' => $spaceapi->state->open,
                ];
                array_merge([$element], $history);
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
                $fi->title = $spaceapi->space . ' ist geöffnet';
            } else {
                $fi->title = $spaceapi->space . ' ist geschlossen';
            }
            $fi->link = $spaceapi->url;
            $fi->date = date('c', $item['date']);
            $rss->addItem($fi);
        }

        $rss->saveFeed('ATOM', $conf['filepath'], false);
        file_put_contents(__DIR__ . '/hist_data', serialize($history));
        echo "Feed successfully generated\n";
    }
} else {
    echo "Could not fetch the SpaceAPI\n";
}