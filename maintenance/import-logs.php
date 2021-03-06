<?php
use \ForceUTF8\Encoding;
chdir(__DIR__.'/..');
include('inc.php');

ORM::configure('mysql:host=127.0.0.1;dbname=loqibot');
ORM::configure('username', '');
ORM::configure('password', '');

$start = time();


$channels = [
  '#indiewebcamp' => [
    'path' => 'freenode/#indieweb',
    'server' => 'freenode',
  ],
  '#indieweb' => [
    'path' => 'freenode/#indieweb',
    'server' => 'freenode',
  ],
  '#indieweb-dev' => [
    'path' => 'freenode/#indieweb-dev',
    'server' => 'freenode',
  ],
  '#indieweb-wordpress' => [
    'path' => 'freenode/#indieweb-wordpress',
    'server' => 'freenode',
  ],
  '#indieweb-meta' => [
    'path' => 'freenode/#indieweb-meta',
    'server' => 'freenode',
  ],
  '#bridgy' => [
    'path' => 'freenode/#bridgy',
    'server' => 'freenode',
  ],
  '#knownchat' => [
    'path' => 'freenode/#knownchat',
    'server' => 'freenode',
  ],
  '#social' => [
    'path' => 'w3c/#social',
    'server' => 'w3c',
  ]
  '#microformats' => [
    'path' => 'freenode/#microformats',
    'server' => 'freenode',
  ],
];

foreach($channels as $channel=>$chinfo) {

  echo "=====================\n";
  echo $channel."\n";

  $batch = 2000;
  $continue = true;
  $last = false;

  $db = new Quartz\DB('data/'.$chinfo['path'], 'w');

  while($continue) {
    $rows = ORM::for_table('irclog')
      ->where_gt('timestamp', $last)
      ->where('channel', $channel)
      // ->where_gt('timestamp', strtotime('2017-10-10T00:00:00+0000').'000')
      // ->where_lt('timestamp', strtotime('2012-01-01T00:00:00+0000').'000')
      ->order_by_asc('timestamp')
      ->limit($batch)
      ->find_many();

    foreach($rows as $row) {
      if($row->spam == 1 || $row->hide == 1) continue;

      switch($row->type) {
        case 64:
          $type = 'join'; break;
        default:
          $type = 'message';
      }

      $date = DateTime::createFromFormat('U.u', floor($row->timestamp/1000).'.'.sprintf('%06d',1000*($row->timestamp%1000)));

      $content = $row->line;

      // Replace some common encoding errors
      $content = str_replace(
        ['â‚¬','â€š','â€¦','â€˜','â€™','â€œ','â€¢','â€“','â€”','â„¢','â€º','â€','Â£',
          'Â©','Âµ','ÃŸ','Ã¤','Ã§','Ã‡','Ã±','Ã¶','Ã¸','Ã¼','Ã¨','Ã©',json_decode("\"\u00c3\u00a0\""),
          'Ã²','Ã³','Ãµ','Ã¹','Ãº','Ã¡','Ã¢','Ãª','Ã´','Ã¿','Ã½'],
        ['€',  '‚',  '…',  '‘',  '’',  '“',  '•',  '–',  '—',  '™',  '›',  '”', '£', 
          '©', 'µ', 'ß', 'ä', 'ç', 'Ç', 'ñ', 'ö', 'ø', 'ü', 'è', 'é', 'á',
          'ò', 'ó', 'õ', 'ù', 'ú', 'á', 'â', 'ê', 'ô', 'ÿ', 'ý'],
        $content
      );


      #echo $row->nick . ': ' . $content ."\n";
      $db->add($date, [
        'type' => $type,
        'timestamp' => $date->format('U.u'),
        'network' => 'irc',
        'server' => $chinfo['server'],
        'channel' => [
          'id' => $channel,
          'name' => $channel,
        ],
        'author' => [
          'uid' => $row->nick,
          'nickname' => $row->nick,
          'name' => $row->nick,
          'username' => null
        ],
        'content' => $content,
      ]);

    }
    if($row) {
      echo $date->format('Y-m-d') . ' ' . $row->timestamp."\n";
      $last = $row->timestamp;
    }

    if(!$rows) {
      echo "Finished!\n";
      $continue = false;
    }
  }

  $seconds = time()-$start;
  echo "Finished in ".$seconds." seconds\n";

}

