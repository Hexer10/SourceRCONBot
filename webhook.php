<?php








/*----------------------EDIT HERE------------------------------*/

//BOT API Token
$token = ''; //Bot token

$user = 'root'; //Database user
$password = ''; //Database password
$ip = '127.0.0.1'; //Database IP
$name =  'telegram'; //Database name


/*----------------------DO NOT EDIT FROM HERE------------------------------*/










define('API_URL', "https://api.telegram.org/bot$token/");

/*          GET DATA            */
$content = file_get_contents("php://input");
$update = json_decode($content, true);
$chatID = $update["message"]["chat"]["id"];
$chatType = $update["message"]["chat"]["type"];
$message = preg_replace('/\s+/', ' ',$update["message"]["text"]);
$username = $update["message"]["chat"]["username"];

$GLOBALS['cid'] = $chatID;

/*          CREATE COMMANDS             */
$commands = array('/addserver', '/delserver', '/rcon', '/serverslist', '/list', '/start', '/help', '/addgroup');
$cmdDesc = array('`Usage: /addserver <Name> <ip> <port> <password>` | `Adds a server to the list.`',
    '`Usage: /addserver <Name>` | `Removes a server to the list.`',
    '`Usage: /rcon <Name> <Command>` | `Send RCON command to a server, the command allows spaces.`',
    '`Usage: /serverslist ` | `Get servers list and information.`',
    '`Usage: /list <Name> <Extended 0(default) | 1>` | `Get player list.`',
    '',
    '`Usage: /help` | `Display this message`',
    '`Usage: /addgroup <groupID> <Name>s` | `Enable /serverslist and /list to the given group`'
);
$cmdArgs = array('4', '1', '2', '0', '1', '0', '0', '1');
$cmdGroup = array(0, 0, 0, 1, 1, 0, 0, 0);



/*      INFO MESSAGE         */
$infoMessage =
    "Hello $username, with this bot you can manage you server!\n\n
These are the available commands:\n";
for($i = 0; $i < count($commands); $i++){
    $count = $i + 1;
    $infoMessage .= "$count. $commands[$i] --> $cmdDesc[$i]\n";
}
$infoMessage .= "\nThe commands params *DON'T* allow spaces!";
/*      END INFO MESSAGE         */


/*      DATABASE                */



$db = new mysqli($ip, $user, $password, $name);

if ($db->connect_errno) {
    sendMessage("Failed to connect to MySQL({$db->connect_errno}): " . $db->connect_error);
    die();
}

$stmt = "CREATE TABLE IF NOT exists servers 
(
  name     varchar(32) not null
    primary key,
  ip       varchar(32) null,
  port     int         null,
  password varchar(32) null,
  owner    varchar(32) null,
  constraint servers_name_uindex
  unique (name)
);
";

if (!$db->query($stmt)) {
    sendMessage("Failed to create servers table({$db->connect_errno}): " . $db->connect_error);
    die();
}

$stmt = "CREATE TABLE IF NOT exists groups 
(
    groupId varchar(32) default '' not null
    primary key,
  owner   varchar(32)            null,
  constraint groups_groupId_uindex
  unique (groupId)
);
";

if (!$db->query($stmt)) {
    sendMessage("Failed to create groups({$db->connect_errno}): " . $db->connect_error);
    die();
}

require_once "SteamCondenser/steam-condenser.php";



/*      GET COMMAND                 */

$explode = explode('@', $message);
if ($explode[1] === 'hexrcon_bot'){
    $message = str_replace('@hexrcon_bot', ' ' , $message);
}

$strpos = strpos($message, ' ');
if (!$strpos) {
    $cmd = substr($message, 0);
} else {
    $cmd = substr($message, 0, $strpos);
}

//'Normal Text'
if (substr($message, 0, 1) !== '/'){
    sendMessage($infoMessage);

    //Valid command
} elseif (in_array($cmd, $commands)) {
    $explode = explode(' ', $message);
    $index = array_search(trim($cmd), $commands);
    if (!$cmdGroup[$index] && strpos($chatType, "group") !== false) {
        sendMessage("Command disabled in groups!");
    } elseif (count($explode) - 1 < $cmdArgs[$index]){
        sendMessage($cmdDesc[$index]);
    } else {
        $name = $explode[1];

        if (strpos($chatType, "group") !== false){
            $stmt = $db->prepare("SELECT * FROM groups WHERE groupId = ?");
            $stmt->bind_param("s", $chatID);
            if (!$stmt->execute()){
                sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                die();
            }


            $result = $stmt->get_result();
            if ($result->num_rows == 0){
                sendMessage("No server found");
                exit();
            }

            $row = $result->fetch_assoc();

            $chatID = $row['owner'];

            $result->close();
            $stmt->close();
        }
        switch ($index){
            //addserver
            case 0:
                $ip = $explode[2];
                $port = $explode[3];
                $password = $explode[4];

                $stmt = $db->prepare("SELECT * FROM servers WHERE owner = ? AND (ip = ? AND port = ?) OR name = ?");
                $stmt->bind_param("ssss", $chatID, $ip, $port, $name);
                if (!$stmt->execute()){
                    sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                    die();
                }

                $result = $stmt->get_result();

                if ($result->num_rows != 0){
                    sendMessage("Server either with the same name or ip:port already existing");
                    exit();
                }
                $result->close();
                $stmt->close();

                try {
                    $rcon = new SourceServer($ip, $port);
                    if (!$rcon->rconAuth($password)) {
                        sendMessage("Error: Invalid RCON password");
                        die();
                    }

                    $stmt = $db->prepare("INSERT INTO servers (name, ip, port, password, owner) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $name,$ip, $port, $password, $chatID);

                    if (!$stmt->execute()){
                        sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                        die();
                    }
                    sendMessage("Server added succesfully");


                } catch (Exception $e) {
                    sendMessage("Error: {$e->getMessage()}");
                    die();
                }

                break;

            //delserver
            case 1:
                $stmt = $db->prepare("DELETE FROM servers WHERE owner = ? AND name = ?");
                $stmt->bind_param("ss", $chatID, $name);

                if (!$stmt->execute()){
                    sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                    die();
                }

                if (!$stmt->affected_rows){
                    sendMessage("Unknown server");
                    die();
                }

                sendMessage("Server successfully removed");
                break;

            //rcon
            case 2:

                $stmt = $db->prepare("SELECT * FROM servers WHERE owner = ? AND name = ?");
                $stmt->bind_param("ss", $chatID,$name);
                if (!$stmt->execute()){
                    sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                    die();
                }

                $result = $stmt->get_result();

                if ($result->num_rows == 0){
                    sendMessage("Unknown server");
                    exit();
                }

                $row = $result->fetch_assoc();
                try {
                    $rcon = new SourceServer($row['ip'], $row['port']);
                    if (!$rcon->rconAuth($row['password'])) {
                        sendMessage("Invalid RCON Password, consider deleting & re-add the server!");
                        die();
                    }
                    $explode = explode(' ', $message, 3);
                    $reply = $rcon->rconExec($explode[2]);


                    sendMessage(substr_count($reply, '\n'). "Server reply:`" .$reply. "`");
                } catch (Exception $e) {
                    sendMessage("Error: {$e->getMessage()}");
                    die();
                }
                break;

            //serverlist
            case 3:
                $stmt = $db->prepare("SELECT * FROM servers WHERE owner = ?");
                $stmt->bind_param("s", $chatID);
                if (!$stmt->execute()){
                    sendMessage("FaQuery failed({$stmt->errno}): " . $stmt->error);
                    die();
                }

                $result = $stmt->get_result();

                if ($result->num_rows == 0){
                    sendMessage("No server added! Use /addserver to add one!");
                    exit();
                }

                $i = 0;
                while ($row = $result->fetch_assoc()){
                    $i++;
                    try {
                        $rcon = new SourceServer($row['ip'], $row['port']);
                        $serverinfo = $rcon->getServerInfo();
                        sendMessage("$i. `{$serverinfo['serverName']}({$row['name']})` Players: `{$serverinfo['numberOfPlayers']}/{$serverinfo['maxPlayers']}` Map: `{$serverinfo['mapName']}`");

                    } catch (Exception $e) {
                        sendMessage("$i. Error:". $e->getMessage());
                    }

                }
                $result->close();
                $stmt->close();
                break;

            //list
            case 4:

                $stmt = $db->prepare("SELECT * FROM servers WHERE owner = ? AND name = ?");
                $stmt->bind_param("ss", $chatID, $name);
                if (!$stmt->execute()){
                    sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                    die();
                }

                $result = $stmt->get_result();

                if ($result->num_rows == 0){
                    sendMessage("Unknown server");
                    exit();
                }

                $i++;
                $row = $result->fetch_assoc();
                try {
                    $rcon = new SourceServer($row['ip'], $row['port']);
                    if (!$rcon->rconAuth($row['password'])) {
                        sendMessage("Invalid RCON Password, consider deleting & re-add the server!");
                        die();
                    }
                    $serverinfo = $rcon->getServerInfo();
                    if ($serverinfo['numberOfPlayers'] == 0){
                        sendMessage("No player online!");
                        die();
                    }

                    $players = $rcon->getPlayers();
                    $output = array();
                    /** @var SteamPlayer $player */
                    $i = 0;
                    foreach ($players as $player) {
                        $i++;
                        $name = $player->getName();
                        $userid = $player->getRealId();
                        $steamid = $player->getSteamId();
                        $ip = $player->getIpAddress();
                        $ping = $player->getPing();
                        $score = $player->getScore();
                        $connTime =  gmdate("H:i:s", round($player->getConnectTime()));

                        $index = (int)floor(getTotalstrlen($output)/4098);
                        if ($explode[2] == 1) {
                            if ($steamid === "BOT") {
                                $output[$index] .= "$i. `$name` UserID: `$userid` SteamID: `$steamid` Score: `$score`\n";
                            } else {
                                $output[$index] .= "$i. Name: `$name` UserID: `$userid` SteamID: `$steamid` IP: `$ip` Score: `$score` Ping: `$ping` OnlineTime: `$connTime`\n";
                            }
                        } else {
                            $output[$index] .= "$i. `$name` \n";
                        }
                    }
                    foreach ($output as $item) {
                        sendMessage($item);
                    }

                } catch (Exception $e) {
                    sendMessage("$i. Error:". $e->getMessage());
                }
                break;

            //start
            case 5:
                sendMessage("Hi!\n Thanks for using this BOT! Type /help to see all the features & commands!");
                break;

            //help
            case 6:
                sendMessage($infoMessage);
                break;

            //addgroup
            case 7:
                $stmt = $db->prepare("INSERT INTO groups (groupId, owner) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $chatID);
                if (!$stmt->execute()){

                    sendMessage("Query failed({$stmt->errno}): " . $stmt->error);
                    die();
                }
                sendMessage("Sever added successfully");
                break;

            default:
                sendMessage("What happened?");
                break;
        }
    }
    //Unexisting command
} else {
    sendMessage("Command not found! Type /help to get a list of the existing commands! ". $cmd);
}

function getTotalstrlen($arr){
    $len = 0;
    foreach ($arr as $item) {
        $len += strlen($item);
    }
    return $len;
}


function sendMessage($text){
    $text = urlencode($text);
    $sendAPI = API_URL . "sendmessage?chat_id={$GLOBALS['cid']}&text=$text&parse_mode=markdown";
    file_get_contents($sendAPI);
}
