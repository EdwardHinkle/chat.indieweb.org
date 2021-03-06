<?php
use \Eventviva\ImageResize;

date_default_timezone_set('UTC');
require_once('vendor/autoload.php');
require_once('lib/Regex.php');
require_once('lib/php_calendar.php');
require_once('lib/format.php');
require_once('lib/config.php');

function mc() {
  static $m;
  if(!isset($m)) {
    $m = new Memcached;
    $m->addServer('127.0.0.1', 11211);
  }
  return $m;
}

class ImageProxy {

  public static function url($url) {
    $signature = hash_hmac('sha256', $url, Config::$secret);
    if(preg_match('/^http/', $url))
      $path = '?url=' . urlencode($url) . '&';
    else
      $path = '/' . $url . '?';
    return Config::$base.'img.php'.$path.'sig=' . $signature;
  }

  public static function image($url, $sig) {
    $expectedSignature = hash_hmac('sha256', $url, Config::$secret);
    if($sig == $expectedSignature) {
      $filename = './public/avatars/'.$sig.'.jpg';

      if(preg_match('/^https?:\/\//', $url)) {

        if(file_exists($filename)) {
          header('Content-type: image/jpeg');
          readfile($filename);
          return;
        }

        $client = new GuzzleHttp\Client();

        try {
          $img = $client->request('GET', $url);

          // Resize to 64px
          $image = ImageResize::createFromString($img->getBody());
          $image->resizeToBestFit(64, 64);

          // Save to disk
          $image->save($filename, IMAGETYPE_JPEG, 80);
          $image->output(IMAGETYPE_JPEG, 80);

          return;
        } catch(GuzzleHttp\Exception\ClientException $e) {
        }
      }
    }
    header('Content-type: image/svg+xml');
    readfile('./public/assets/user.svg');
  }

}

function getViewerTimezone() {
  try {
    $tzname = array_key_exists('timezone_view', $_COOKIE) ? $_COOKIE['timezone_view'] : 'UTC';
    $tz = new DateTimeZone($tzname);
  } catch(Exception $e) {
    $tzname = 'UTC';
    $tz = new DateTimeZone('UTC');
  }
  return [$tzname, $tz];
}

function filterText($text, $channel) {
	/*
	for($i=0; $i<strlen($text); $i++) {
		if(ord($text[$i]) < 32)
			$text[$i] = '';
	}
	*/

	$text = htmlspecialchars($text, ENT_SUBSTITUTE, 'UTF-8');
  #$text = mb_encode_numericentity($text);

  // Remove `/me ` from the beginning of lines
  $text = preg_replace('/^\/me /', '', $text);

  $text = preg_replace('/({.+})/', '<code>$1</code>', $text);

	$text = preg_replace(Regex_URL::$expression, Regex_URL::$replacement, $text);
	$text = preg_replace(Regex_Twitter::$expression, Regex_Twitter::$replacement, $text);

  if($b=Config::wiki_base($channel)) {
    $wikireplace = str_replace('{{wikibase}}', $b, Regex_WikiPage::$replacement);
  	$text = preg_replace(Regex_WikiPage::$expression, $wikireplace, $text);
  }

  // Expand Loqi memes
  $text = preg_replace('/(?<!\")(http:\/\/meme\.loqi\.me\/m\/[a-zA-Z0-9_]+\.(jpg|gif|png))/', '$1<br><img src="$1" style="max-width: 200px; vertical-align: top; margin-left: 80px;">', $text);
	
	return $text;
}

function isMeMessage($text) {
  return preg_match('/^\/me /', $text);
}

function xmlEscapeText($text, $autolink=TRUE) {
	# escape the source line of text
	$text = str_replace(array('&','<','>','"'), array('&amp;','&lt;','&gt;','&quot;'), $text);
	
	if($autolink) {
		# add links for URLs and twitter names
		$text = preg_replace(Regex_URL::$expression, Regex_URL::$replacement, $text);
		$text = preg_replace(Regex_Twitter::$expression, Regex_Twitter::$replacement, $text);
	}
	
	return $text;
}

function stripIRCControlChars($text) {
	$text = preg_replace('/\x03\d{1,2}/', '', $text);
	$text = preg_replace('/\x03/', '', $text);
	return $text;
}

function trimString($str, $length, $allow_word_break=false) {
// trims $str to $length characters
// if $str is too long, it puts … on the end
// if $allow_word_break is true, doesn't split a word in the middle

	if( strlen($str) <= $length ) {
		return $str;
	} else {
		if( $allow_word_break ) {
			return trim(substr($str,0,$length-3))."...";
		} else {
			$newstr = substr($str,0,$length-3);
			return substr($newstr, 0, strrpos($newstr, " "))."...";
		}
	}
}


function refreshUsers() {
  $collections = [
    ['group'=>'indieweb', 'url'=>'https://indieweb.org/irc-people'],
    ['group'=>'w3c', 'url'=>'https://www.w3.org/wiki/IRC-people']
  ];

  foreach($collections as $c) {
  	$parsed = file_get_contents('http://pin13.net/mf2/?url='.urlencode($c['url']));
  	if(trim($parsed)) {
  	  $data = json_decode($parsed);
  	  if($data && property_exists($data, 'items') && count($data->items) && count($data->items[0])) {
        $item = $data->items[0];
        if(isset($item->children))
          $users = $item->children;
        else
          $users = $data->items;

  	    $er = fopen('php://stderr', 'w');
  	    fputs($er, 'found ' . count($users) . ' items'."\n");
  	    fclose($er);
  			file_put_contents(dirname(__FILE__).'/data/'.$c['group'].'.json', json_encode($users,JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
  		}
  	}
  }
}

function join_with_and($array) {
  if(count($array) == 0) return '';
  if(count($array) == 1) return $array[0];
  $last = array_pop($array);
  return implode(', ', $array).' and '.$last;
}

/**
 * Generate file that contains the date with the first message
 * in each channel
 */
/* TODO: update to check files on disk and save as YYYY-MM-DD
function refreshFirst() {
    $channels = Config::supported_channels();
    $dates    = new stdClass();

    foreach ($channels as $channel) {
        if ($channel == 'indieweb') {
            $sqlChannels = '"#indieweb","#indiewebcamp"';
        } else {
            $sqlChannels = '"' . Config::irc_channel_for_slug($channel) . '"';
        }
        $sql = 'SELECT timestamp FROM irclog'
            . ' WHERE channel IN (' . $sqlChannels . ')'
            . ' ORDER BY timestamp ASC'
            . ' LIMIT 1';
        $stmt = db()->query($sql);
        $row = $stmt->fetchObject();
        if (is_object($row)) {
            $dates->{'#' . $channel} = strtotime(date('Y-m-d', ((int) $row->timestamp / 1000))) * 1000;
        }
    }
    file_put_contents(__DIR__ . '/data/first.json', json_encode($dates));
}
*/

function isAfterFirst($channel, $date)
{
    $data = json_decode(file_get_contents(__DIR__ . '/data/first.json'));
    if (!isset($data->$channel)) {
        return false;
    }

    return strtotime($date) >= strtotime($data->$channel);
}

$users = array();

function loadUsers($channel) {
	global $users;

  if($group=Config::group_for_channel($channel)) {
    $filename = dirname(__FILE__).'/data/'.$group.'.json';
    if(file_exists($filename)){
    	$data = json_decode(file_get_contents($filename));
    	foreach($data as $item) {
    		if(in_array('h-card', $item->type)) {
    			$users[] = $item;
    		}
    	}
    }
  }
}

function loadTimezones() {
  global $users;
  
  $timezones = [];
  foreach($users as $u) {
    if(property_exists($u->properties, 'tz')) {
      $t = $u->properties->tz[0];
      if(!in_array($t, $timezones)) {
        try {
          new DateTimeZone($t);
          $timezones[] = $t;
        } catch(Exception $e) {}
      }
    }
  }
  sort($timezones);
  return $timezones;
}

function userForNick($nick) {
	global $users;

  $nick = strtolower(trim($nick,'_[]'));

	foreach($users as $u) {
		if(@strtolower($u->properties->nickname[0]) == $nick) {
			return $u;
		}
	}
	return null;
}

function userForHost($host) {
  global $users;
  
	foreach($users as $u) {
	  $userHost = property_exists($u->properties, 'url') ? preg_replace('/https?:\/\//','', strtolower($u->properties->url[0])) : false;
		if($userHost && $userHost == strtolower($host)) {
			return $u;
		}
	}
	return null;
}

function debug($thing) {
  if($_SERVER['REMOTE_ADDR'] == '24.21.213.88') {
    var_dump($thing);
  }
}
