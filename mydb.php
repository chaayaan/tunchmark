<?php
    mysqli_report(MYSQLI_REPORT_OFF);   // ADDED: hide PHP warnings/exceptions
    ob_start();                         // ADDED

    ini_set('date.timezone', 'Asia/Dhaka');      // PHP timezone
    date_default_timezone_set('Asia/Dhaka');     // PHP timezone (backup)
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "billing_app";

    $conn = mysqli_connect($host, $user, $pass, $db);

    if (!$conn) {
        error_log("Connection failed: " . mysqli_connect_error());
        ob_end_clean();                 // discard any warning text
        http_response_code(503);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Under Maintenance</title>
<style>
  body{background:#fff;font-family:Arial,sans-serif;text-align:center;padding:20px}
  .note{background:#ffedd5;border:1px solid #fb923c;color:#7c2d12;padding:12px;border-radius:8px;max-width:360px;margin:0 auto 20px}
  #box{position:relative;width:300px;height:300px;margin:10px auto;border:2px solid #ccc;border-radius:8px;overflow:hidden}
  #bug{position:absolute;font-size:34px;cursor:pointer;user-select:none}
</style>
</head>
<body>
  <div class="note">
    &#9888;&#65039; The server is currently under <b>maintenance</b>.<br>
    Please contact <b>software support</b> as soon as possible.
  </div>

  <h3>Catch the bug! &#128027;</h3>
  <p>Score: <b id="score">0</b></p>
  <div id="box"><span id="bug">&#128027;</span></div>

<script>
  var score = 0, bug = document.getElementById('bug'), box = document.getElementById('box');
  function move() {
    bug.style.left = Math.random() * (box.clientWidth - 40) + 'px';
    bug.style.top  = Math.random() * (box.clientHeight - 40) + 'px';
  }
  bug.onclick = function () {
    score++;
    document.getElementById('score').textContent = score;
    move();
  };
  move();
  setInterval(move, 1000);
</script>
</body>
</html>
<?php
        exit;
    }

    mysqli_set_charset($conn, 'utf8mb4');
    
    /* SET Bangladesh timezone (SESSION LEVEL) */
    mysqli_query($conn, "SET time_zone = '+06:00'");

    ob_end_flush();                     // ADDED
?>