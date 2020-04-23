<?
$config = array(
  'alphabet' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
  'human_db' => __DIR__ . '/blunk.db',
  'human_path' => '/blunk/',
  'mole_db' => __DIR__ . '/blink.db',
  'mole_path' => '/blink/',
);

include(__DIR__ . '/config.php');
include(__DIR__ . '/../lib/include.php');

function blink_long($short) {
  global $pdo;

  $result = $pdo->prepare('SELECT `long` FROM `blink` WHERE `short` = :short');

  $result->execute(array(
    ':short' => $short
  ));

  return $result->fetch(PDO::FETCH_COLUMN);
}

function blink_short($long, $alphabet) {
  global $pdo;

  $result = $pdo->prepare('SELECT `short` FROM `blink` WHERE `long` = :long');

  $result->execute(array(
    ':long' => $long
  ));

  $short = $result->fetch(PDO::FETCH_COLUMN);

  if (!$short) {
    for ($i = (int) log((int) $pdo->query('SELECT COUNT(*) FROM `blink`')->fetch(PDO::FETCH_COLUMN) + 1, strlen($alphabet)) + 1; $pdo->query("SELECT COUNT(*) FROM `blink` WHERE LENGTH(`short`) > $i")->fetch(PDO::FETCH_COLUMN); $i++);
    $result = $pdo->prepare('SELECT COUNT(*) FROM `blink` WHERE `short` = :short');

    do {
      $short = substr(str_shuffle($alphabet), 0, $i);

      $result->execute(array(
        ':short' => $short
      ));
    } while ($result->fetch(PDO::FETCH_COLUMN));

    $result = $pdo->prepare('INSERT INTO `blink` (`long`, `short`) VALUES (:long, :short)');

    $result->execute(array(
      ':long' => $long,
      ':short' => $short
    ));
  }

  return $short;
}

function blink_view($short) {
  global $pdo;

  $result = $pdo->prepare('UPDATE `blink` SET `freq` = `freq` + 1 WHERE `short` = :short');

  $result->execute(array(
    ':short' => $short
  ));
}

if (@$_POST['type'] == 'human' or strpos($_SERVER['REQUEST_URI'], $config['human_path']) === 0) {
  if ($_SERVER['REQUEST_METHOD'] == 'GET' and !isset($_GET['u'])) {
    header('Location: ' . $config['mole_path']);
    die();
  }

  $db = $config['human_db'];
  $path = $config['human_path'];
} else {
  $db = $config['mole_db'];
  $path = $config['mole_path'];
}

$create = !file_exists($db);
$short = false;
$pdo = new PDO('sqlite:' . $db);

if ($create) {
  $pdo->exec(<<<EOF
CREATE TABLE `blink` (
  `long` varchar(255) UNIQUE NOT NULL,
  `short` varchar(16) PRIMARY KEY NOT NULL,
  `freq` unsigned int(10) NOT NULL DEFAULT '0'
)
EOF
    );
}

if (@$_REQUEST['u']) {
  if (@$_GET['u']) {
    $success = false;

    if ($path == $config['mole_path']) {
      $success = true;
    } elseif (isset($_GET['token'])) {
      $ch = curl_init();

      $fields = array(
        'response' => $_GET['token'],
        'secret' => $config['secret']
      );

      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
      $response = json_decode(curl_exec($ch));
      $success = $response->success;
    }

    if ($success) {
      if ($url = blink_long($_GET['u'])) {
        blink_view($_GET['u']);
        header('Location: ' . $url);
        die();
      }
    }
  }

  if (@$_POST['u'] and $url = filter_input(INPUT_POST, 'u', FILTER_SANITIZE_URL)) {
    if (!preg_match('/^\w+:\/\//', $url)) {
      $url = 'http://' . $url;
    }

    $short = blink_short($url, $config['alphabet']);
  }
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
<?
print_head('Blink');
?>  </head>
  <body>
    <div id="main">
      <h1>Blink</h1>
<?
$subtitles = array(
  'Baby Shoes',
  'Beats TinyURL',
  'Gracious Links',
  'Less is More',
  'Malcolm Gladwell',
  'Oven Aye',
  'Short and Sweet',
  'Shortens Links',
  'Size Matters',
  'Welcome FBI'
);

$subtitle = $subtitles[mt_rand(0, count($subtitles) - 1)];

echo <<<EOF
      <h2>$subtitle</h2>

EOF;

if (@$_GET['u']) {
  echo <<<EOF
      <script type="text/javascript" src="https://www.google.com/recaptcha/api.js?render=$config[key]"></script>
      <script type="text/javascript">// <![CDATA[
        grecaptcha.ready(function() {
          grecaptcha.execute('$config[key]', {action: 'homepage'}).then(function(token) {
            $.get('./', {u: '$_GET[u]', token: token}, function(url) {
              window.location = url;
            });
          });
        });
      // ]]></script>

EOF;
} elseif (isset($_GET['list'])) {
  echo <<<EOF
      <div>
        <p>All URLs are listed below.</p>
        <table class="table">
          <tr>
            <th style="width: 80%;">Long URL</th>
            <th>Short URL</th>
            <th>Hits</th>
          </tr>

EOF;

  $result = $pdo->query('SELECT * FROM `blink` ORDER BY `freq` DESC');

  while ($row = $result->fetch()) {
    $url = htmlentities($row['long'], NULL, 'UTF-8');

    echo <<<EOF
          <tr>
            <td>$url</td>
            <td>$row[short]</td>
            <td>$row[freq]</td>
          </tr>

EOF;
  }

  echo <<<EOF
        </table>
      </div>

EOF;
} else {
  $options = '';

  if ($short) {
    $s = @$_SERVER['HTTPS'] ? 's' : '';
    $label = 'Short URL';
    $options = " value=\"http$s://$_SERVER[HTTP_HOST]$path?u=$short\" readonly=\"readonly\"";
  } else {
    $label = 'Long URL';
    $options = <<<EOF
 />
          </div>
        </div>
        <div class="form-control">
          <div class="input-group">
            <input type="radio" id="mole" name="type" value="mole" checked="checked" />
            <label for="mole">Restrict by credentials (moles only)</label>
          </div>
          <div class="input-group">
            <input type="radio" id="human" name="type" value="human" />
            <label for="human">Restrict by CAPTCHA (humans only)</label>
          </div>
        </div>
        <div class="form-control">
          <div class="input-group">
            <input type="submit" value="Shorten"
EOF;
  }

  echo <<<EOF
      <form action="./" method="post">
        <div class="form-control">
          <label for="u">$label</label>
          <div class="input-group">
            <input type="text" id="u" name="u"$options />
          </div>
        </div>
      </form>

EOF;
}
?>    </div>
<?
print_footer(
  'Copyright &copy; 2015 Keegan Ryan and Will Yu',
  'A service of Blacker House'
);
?>  </body>
</html>
