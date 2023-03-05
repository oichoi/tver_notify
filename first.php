<?PHP
namespace tverrss;

//tver api end point
const TVER_CREATE = 'https://platform-api.tver.jp/v2/api/platform_users/browser/create';
const TVER_SEARCH = 'https://platform-api.tver.jp/service/api/v1/callKeywordSearch';

//User-Agent
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0';
const CURL_TIMEOUT = 10000; #ミリ秒

//curlのデフォルトオプション
const CURL_OPTIONS = [
  CURLOPT_FAILONERROR => True,
  CURLOPT_FOLLOWLOCATION => True,
  CURLOPT_RETURNTRANSFER => True,
  CURLOPT_CONNECTTIMEOUT_MS => CURL_TIMEOUT,
  CURLOPT_MAXREDIRS => 5,
  CURLOPT_TIMEOUT_MS => CURL_TIMEOUT,
  CURLOPT_USERAGENT => UA,
];

const RSS2HEAD = <<<'HEAD'
<?xml version="1.0"?>
<rss version="2.0">
<channel>
<title>TVerの番組新着リスト</title>
<link>https://tver.jp/</link>
<description>tver.jpのapiから新着をお知らせします</description>
<language>ja-jp</language>
HEAD;

const RSS2FOOT = <<<'FOOT'
</channel>
</rss>
FOOT;


function is_valid_url($url){
    return false !== filter_var($url, FILTER_VALIDATE_URL) && 0 === strpos($url, "https://");
}

function h($s){
  if($s && is_string($s)){
    return htmlspecialchars($s, ENT_QUOTES|ENT_XML1);
  }else{
    return "";
  }
}

function dl(string $url, array $options=[]){
  
  if(!is_valid_url($url)){
    throw new \Exception("無効なURL");
  }
  
  $options = array_replace(CURL_OPTIONS, $options);
  $options[CURLOPT_URL] = $url;

  $ch = curl_init();
  curl_setopt_array($ch, $options);

  $result = curl_exec($ch);

  if($result === False){
    echo curl_error($ch);
  }

  curl_close($ch);

  return $result;
}

function create_userdata(){
  static $userdata = [];

  if ($userdata) {
    return $userdata;
  }

  #新しくuser登録
  $options = [
    CURLOPT_POST => True,
    CURLOPT_POSTFIELDS => 'device_type=pc',
    CURLOPT_HTTPHEADER => ['Accept: */*', 'Referer: https://s.tver.jp/', 'Origin: https://s.tver.jp', 'Content-Type: application/x-www-form-urlencoded', 'Connection: keep-alive', 'Sec-Fetch-Dest: empty', 'Sec-Fetch-Mode: cors', 'Sec-Fetch-Site: same-site'],
  ];

  $json = dl(TVER_CREATE, $options);

  if ($json){
    $data = json_decode($json, true);
    $userdata = $data["result"];
  }

  return $userdata;
}


function get_list(){
  $userdata = create_userdata();
  $query = http_build_query([
    'platform_uid' => $userdata['platform_uid'],
    'platform_token' => $userdata['platform_token'],
    'keyword' => 'クセ',
    'require_data' => 'later',
  ]);

  $url = TVER_SEARCH . "?{$query}";

  $header = [
    CURLOPT_HTTPHEADER => ['x-tver-platform-type: web'],
  ];

  $json = json_decode(dl($url, $header), true);
  
  return $json["result"]["contents"];
  //return $json;
}

function get_rss2(array $data, int $max=100){
  $rss_head = RSS2HEAD;
  $rss_foot = RSS2FOOT;
  $items = "";
  $count = 0;

  foreach($data as $d){
    if ($count >= $max){
      break;
    }
    $count = $count + 1;
    $content = $d["content"];

    if (!$content["isAvailable"]) {
      continue;
    }

    $title = h($content["title"]);
    $series_title  = h($content["seriesTitle"]);
    $id = h($content["id"]);
    $version = h($content["version"]);
    if (!$version) {
      $version = 5;
    }
    if (isset($content["broadcastDateLabel"])){
      $date = h($content["broadcastDateLabel"]);
    }else{
      $date = "unknown";
    }
    $provider = h($content["productionProviderName"]);
    $url = "https://tver.jp/episodes/{$id}";

    $long_title = "{$provider} - {$series_title} - {$title}";
    $images_tag = "<img src='https://statics.tver.jp/images/content/thumbnail/episode/small/{$id}.jpg?v={$version}' alt='{$title}'/>";

    $description = <<<DESC
<a href="{$url}">{$images_tag}</a><br/>
<h2><a href="{$url}">{$series_title} - {$title}</a></h2>
DESC;
    $description = h($description);
    $items .= <<<EOD
<item>
<title>{$long_title}</title>
<link>{$url}</link>
<guid isPermaLink="true">{$url}</guid>
<description>{$description}</description>
</item>
EOD;
  }

  return <<<EOC
{$rss_head}
{$items}
{$rss_foot}
EOC;
}

function rss_header(){
  header('Content-Type: application/rss+xml');
}

function main(){
  $list = get_list();
  rss_header();
  echo get_rss2($list);
  //print_r($list);
}

main();
