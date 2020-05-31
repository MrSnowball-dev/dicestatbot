<?php
ini_set('display_errors', 1);
include 'config.php';
header('Content-Type: text/html; charset=utf-8');

$api = 'https://api.telegram.org/bot'.$tg_bot_token;

$input = file_get_contents('php://input');
$output = json_decode($input, TRUE); //сюда приходят все запросы по вебхука

//телеграмные события
$chat_id = isset($output['message']['chat']['id']) ? $output['message']['chat']['id'] : 'chat_id_empty'; //отделяем id чата, откуда идет обращение к боту
$chat = isset($output['message']['chat']['title']) ? $output['message']['chat']['title'] : 'chat_title_empty';
$chat_type = isset($output['message']['chat']['type']) ? $output['message']['chat']['type'] : 'chat_type_empty';
$new_chat_title = isset($output['message']['new_chat_title']) ? $output['message']['new_chat_title'] : 'new_chat_title_empty';
$message = isset($output['message']['text']) ? $output['message']['text'] : 'message_text_empty'; //сам текст сообщения
$user = isset($output['message']['from']['username']) ? $output['message']['from']['username'] : 'origin_user_empty';
$user_language_code = isset($output['message']['from']['language_code']) ? $output['message']['from']['language_code'] : 'no_language_set';
$user_id = isset($output['message']['from']['id']) ? $output['message']['from']['id'] : 'origin_user_id_empty';
$message_id = isset($output['message']['message_id']) ? $output['message']['message_id'] : 'message_id_empty';
$dice = isset($output['message']['dice']) ? $output['message']['dice'] : 'dice_empty';
$dice_emoji = isset($output['message']['dice']['emoji']) ? $output['message']['dice']['emoji'] : 'dice_emoji_empty';
$dice_result = isset($output['message']['dice']['value']) ? $output['message']['dice']['value'] : 'dice_value_empty';

$callback_query = isset($output['callback_query']) ? $output['callback_query'] : 'callback_query_empty'; //сюда получаем все, что приходит от inline клавиатуры
$callback_id = isset($callback_query['id']) ? $callback_query['id'] : 'callback_id_empty';
$callback_data = isset($callback_query['data']) ? $callback_query['data'] : 'callback_data_empty'; //ответ от клавиатуры идет сюда
$callback_chat_id = isset($callback_query['message']['chat']['id']) ? $callback_query['message']['chat']['id'] : 'callback_chat_id_empty'; //id чата, где был вызов клавиатуры
$callback_user_id = isset($callback_query['from']['id']) ? $callback_query['from']['id'] : 'callback_user_id_empty'; //id чата, где был вызов клавиатуры
$callback_message_text = isset($callback_query['message']['text']) ? $callback_query['message']['text'] : 'callback_message_text_empty'; //оригинальное сообщение с клавой
$callback_message_id = isset($callback_query['message']['message_id']) ? $callback_query['message']['message_id'] : 'callback_message_id_empty'; //id того сообщения, в котором нажата кнопка клавиатуры

echo "Init successful.\n";

//----------------------------------------------------------------------------------------------------------------------------------//


$markdownify_array = [
	//In all other places characters '_‘, ’*‘, ’[‘, ’]‘, ’(‘, ’)‘, ’~‘, ’`‘, ’>‘, ’#‘, ’+‘, ’-‘, ’=‘, ’|‘, ’{‘, ’}‘, ’.‘, ’!‘ must be escaped with the preceding character ’\'.
	'>' => "\>",
	'#' => "\#",
	'+' => "\+",
	'-' => "\-",
	'=' => "\=",
	'|' => "\|",
	'{' => "\{",
	'}' => "\}",
	'.' => "\.",
	'!' => "\!",
	'_' => "\_",
	'*' => "\*",
	'[' => "\[",
	']' => "\]",
	'(' => "\(",
	')' => "\)",
	'~' => "\~",
	'`' => "\`",
];

if ($message == '/start') {
	switch ($user_language_code) {
		case 'ru':
			sendMessage($chat_id, "Чтобы начать собирать статистику по 🎲, 🎯 и 🏀 добавьте меня в любой чат\.", NULL);
			break;
		
		default:
			sendMessage($chat_id, "To start gathering 🎲, 🎯 and 🏀 results, add me to any chat\.", NULL);
	}
}


if ($dice !== 'dice_empty') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_query($db, "select status, count, type from tournament_status where chat_id=".$chat_id);
	$tournament_status = mysqli_fetch_all($query, MYSQLI_ASSOC);

	if ($tournament_status[0]['status'] == 'ended' || $tournament_status[0]['status'] == 'never_ran' || $tournament_status[0] == NULL) {
		switch ($dice_emoji) {
			case "🎲":
				$query = mysqli_query($db, "select distinct chat_id from dice_stats where chat_id=".$chat_id);
				while ($sql = mysqli_fetch_object($query)) {
					$dice_chat = $sql->chat_id;
				}
	
				if ($dice_chat !== NULL) {
					$query = mysqli_query($db, "select user_id from dice_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_user[] = $sql->user_id;
					}
					if (in_array($user_id, $dice_user)) {
						mysqli_query($db, "update dice_stats set dice_".$dice_result."=dice_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
					} else {
						mysqli_query($db, "insert into dice_stats (chat_id, user_id, dice_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
				} else {
					mysqli_query($db, "insert into dice_stats (chat_id, user_id, dice_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					if ($tournament_status[0] == NULL) {
						mysqli_query($db, "insert into tournament_status (chat_id) values (".$chat_id.")");
					}
				}
	
				break;
			
			case "🎯":
				$query = mysqli_query($db, "select distinct chat_id from darts_stats where chat_id=".$chat_id);
				while ($sql = mysqli_fetch_object($query)) {
					$dice_chat = $sql->chat_id;
				}
	
				if ($dice_chat !== NULL) {
					$query = mysqli_query($db, "select user_id from darts_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_user[] = $sql->user_id;
					}
					if (in_array($user_id, $dice_user)) {
						mysqli_query($db, "update darts_stats set darts_".$dice_result."=darts_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
					} else {
						mysqli_query($db, "insert into darts_stats (chat_id, user_id, darts_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
				} else {
					mysqli_query($db, "insert into darts_stats (chat_id, user_id, darts_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					if ($tournament_status[0] == NULL) {
						mysqli_query($db, "insert into tournament_status (chat_id) values (".$chat_id.")");
					}
				}
	
				break;
	
			case "🏀":
				$query = mysqli_query($db, "select distinct chat_id from basket_stats where chat_id=".$chat_id);
				while ($sql = mysqli_fetch_object($query)) {
					$dice_chat = $sql->chat_id;
				}
	
				if ($dice_chat !== NULL) {
					$query = mysqli_query($db, "select user_id from basket_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_user[] = $sql->user_id;
					}
					if (in_array($user_id, $dice_user)) {
						mysqli_query($db, "update basket_stats set basket_".$dice_result."=basket_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
					} else {
						mysqli_query($db, "insert into basket_stats (chat_id, user_id, basket_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
				} else {
					mysqli_query($db, "insert into basket_stats (chat_id, user_id, basket_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					if ($tournament_status[0] == NULL) {
						mysqli_query($db, "insert into tournament_status (chat_id) values (".$chat_id.")");
					}
				}
	
				break;
		}
	} elseif ($tournament_status[0]['status'] == 'ongoing') {
		if ($tournament_status[0]['type'] == 'dice') {
			switch ($dice_emoji) {
				case "🎲":
					$query = mysqli_query($db, "select distinct chat_id from dice_tournament_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_chat = $sql->chat_id;
					}
		
					if ($dice_chat !== NULL) {
						$query = mysqli_query($db, "select user_id, dice_1+dice_2+dice_3+dice_4+dice_5+dice_6 as dice_sum from dice_tournament_stats where chat_id=".$chat_id." and user_id=".$user_id);
						while ($sql = mysqli_fetch_object($query)) {
							$dice_user[] = $sql->user_id;
							$dice_sum = $sql->dice_sum;
						}
						if (in_array($user_id, $dice_user)) {
							if ($dice_sum < $tournament_status[0]['count']) {
								mysqli_query($db, "update dice_tournament_stats set dice_".$dice_result."=dice_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
							} else {
								sendReply($chat_id, "_Sorry, you have depleted your ".$tournament_status[0]['count']." tries in an ongoing tournament\. Please, wait for the admin to end the tournament\!_", $message_id);
							}
						} else {
							mysqli_query($db, "insert into dice_tournament_stats (chat_id, user_id, dice_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
						}
					} else {
						mysqli_query($db, "insert into dice_tournament_stats (chat_id, user_id, dice_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
				break;
				
				default:
					sendReply($chat_id, "_Sorry, there's an ongoing 🎲 tournament in this chat\. To participate \- send a 🎲 emoji, or wait for an admin to end the tournament\._", $message_id);
				break;
			}
		} elseif ($tournament_status[0]['type'] == 'darts') {
			switch ($dice_emoji) {
				case "🎯":
					$query = mysqli_query($db, "select distinct chat_id from darts_tournament_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_chat = $sql->chat_id;
					}
		
					if ($dice_chat !== NULL) {
						$query = mysqli_query($db, "select user_id, darts_1+darts_2+darts_3+darts_4+darts_5+darts_6 as dice_sum from darts_tournament_stats where chat_id=".$chat_id." and user_id=".$user_id);
						while ($sql = mysqli_fetch_object($query)) {
							$dice_user[] = $sql->user_id;
							$dice_sum = $sql->dice_sum;
						}
						if (in_array($user_id, $dice_user)) {
							if ($dice_sum < $tournament_status[0]['count']) {
								mysqli_query($db, "update darts_tournament_stats set darts_".$dice_result."=darts_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
							} else {
								sendReply($chat_id, "_Sorry, you have depleted your ".$tournament_status[0]['count']." tries in an ongoing tournament\. Please, wait for the admin to end the tournament\!_", $message_id);
							}
						} else {
							mysqli_query($db, "insert into darts_tournament_stats (chat_id, user_id, darts_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
						}
					} else {
						mysqli_query($db, "insert into darts_tournament_stats (chat_id, user_id, darts_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
					break;
				
				default:
					sendReply($chat_id, "_Sorry, there's an ongoing 🎯 tournament in this chat\. To participate \- send a 🎯 emoji, or wait for an admin to end the tournament\._", $message_id);
					break;
			}
		} elseif ($tournament_status[0]['type'] == 'basket') {
			switch ($dice_emoji) {
				case "🏀":
					$query = mysqli_query($db, "select distinct chat_id from basket_tournament_stats where chat_id=".$chat_id);
					while ($sql = mysqli_fetch_object($query)) {
						$dice_chat = $sql->chat_id;
					}
		
					if ($dice_chat !== NULL) {
						$query = mysqli_query($db, "select user_id, basket_1+basket_2+basket_3+basket_4+basket_5 as dice_sum from basket_tournament_stats where chat_id=".$chat_id." and user_id=".$user_id);
						while ($sql = mysqli_fetch_object($query)) {
							$dice_user[] = $sql->user_id;
							$dice_sum = $sql->dice_sum;
						}
						if (in_array($user_id, $dice_user)) {
							if ($dice_sum < $tournament_status[0]['count']) {
								mysqli_query($db, "update basket_tournament_stats set basket_".$dice_result."=basket_".$dice_result."+1 where user_id=".$user_id." and chat_id=".$chat_id);
							} else {
								sendReply($chat_id, "_Sorry, you have depleted your ".$tournament_status[0]['count']." tries in an ongoing tournament\. Please, wait for the admin to end the tournament\!_", $message_id);
							}
						} else {
							mysqli_query($db, "insert into basket_tournament_stats (chat_id, user_id, basket_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
						}
					} else {
						mysqli_query($db, "insert into basket_tournament_stats (chat_id, user_id, basket_".$dice_result.") values (".$chat_id.", ".$user_id.", 1)");
					}
					break;
				
				default:
					sendReply($chat_id, "_Sorry, there's an ongoing 🏀 tournament in this chat\. To participate \- send a 🏀 emoji, or wait for an admin to end the tournament\._", $message_id);
					break;
			
			}
		}

	} else {
		sendReply($chat_id, "_Please wait until admin starts the tournament for your result to be recorded\!_", $message_id);
	}
	
	mysqli_free_result($query);
	mysqli_close($db);
}

if ($message == '/tournament' || $message == '/tournament@dicestatbot') {
	if ($chat_type == 'private') {
		switch ($user_language_code) {
			case 'ru':
				sendMessage($chat_id, "_Извините, турниры доступны только в групповых чатах\._");
				break;
			
			default:
				sendMessage($chat_id, "_Sorry, tournaments are only available in group chats\._");
				break;
		}
	} else {
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$user_id), TRUE);

		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";
	
			mysqli_query($db, "update tournament_status set status='started' where chat_id=".$chat_id);
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '🎲', 'callback_data' => 'init_dice_tournament'], ['text' => '🎯', 'callback_data' => 'init_darts_tournament'], ['text' => '🏀', 'callback_data' => 'init_basket_tournament']]
			]];
			sendMessage($chat_id, "You initiated a tournament in this chat\!\n\nPlease choose which type of tournament you want to host:", $tournament_keyboard);
			
			mysqli_close($db);
		} else {
			sendReply($chat_id, "_Sorry, only admins can initiate tournaments in chats\._", $message_id);
		}
	}
}

if ($message == '/stats' || $message == '/stats@dicestatbot') {
	$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$chat_id), TRUE);
    $title = $info['result']['title'];

	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$query = mysqli_query($db, "SELECT * FROM dice_stats WHERE chat_id=".$chat_id." ORDER BY (dice_1+dice_2+dice_3+dice_4+dice_5+dice_6) DESC LIMIT 10");
	$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

	$display_string_start = "🏆 *Top ".count($stats)."* 🎲 players by _*throws*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
	$display_string = "";

	if (count($stats) == NULL) {
		$display_string .= "_No 🎲 plays registered_";
	} else {
		foreach ($stats as $key => $value) {
			$sum[$key] = $stats[$key]['dice_1']+$stats[$key]['dice_2']+$stats[$key]['dice_3']+$stats[$key]['dice_4']+$stats[$key]['dice_5']+$stats[$key]['dice_6'];
			$points_sum[$key] = $stats[$key]['dice_1']+($stats[$key]['dice_2']*2)+($stats[$key]['dice_3']*3)+($stats[$key]['dice_4']*4)+($stats[$key]['dice_5']*5)+($stats[$key]['dice_6']*6);
			$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
			$members[$key] = $member['result']['user']['first_name'];
			switch ($key) {
				case 0:
					$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
					break;
	
				case 1:
					$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
					break;
				
				case 2:
					$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n───────────────────────\n";
					break;
				
				default:
					$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
					break;
			}
		}
	}

	$stat_switcher_keyboard = ['inline_keyboard' => [
        [['text' => '● 🎲 Throws ●', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
        [['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
	]];

	sendMessage($chat_id, $display_string_start.$display_string, $stat_switcher_keyboard);

	mysqli_free_result($stats);
	mysqli_close($db);
}





if ($message == '/mystats' || $message == '/mystats@dicestatbot') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";

	$user_stats_for_chat = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM dice_stats WHERE chat_id=".$chat_id." and user_id=".$user_id), MYSQLI_ASSOC);
	$user_global_stats = mysqli_fetch_all(mysqli_query($db, "SELECT chat_id, user_id, SUM(dice_1) as dice_1, SUM(dice_2) as dice_2, SUM(dice_3) as dice_3, SUM(dice_4) as dice_4, SUM(dice_5) as dice_5, SUM(dice_6) as dice_6 FROM `dice_stats` WHERE user_id=".$user_id), MYSQLI_ASSOC);
	$user_chats = mysqli_fetch_all(mysqli_query($db, "SELECT chat_id FROM dice_stats WHERE user_id=".$user_id), MYSQLI_ASSOC);

	$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$user_id), TRUE);

	$chat_throw_sum = $user_stats_for_chat[0]['dice_1']+$user_stats_for_chat[0]['dice_2']+$user_stats_for_chat[0]['dice_3']+$user_stats_for_chat[0]['dice_4']+$user_stats_for_chat[0]['dice_5']+$user_stats_for_chat[0]['dice_6'];
	$chat_points_sum = $user_stats_for_chat[0]['dice_1']+($user_stats_for_chat[0]['dice_2']*2)+($user_stats_for_chat[0]['dice_3']*3)+($user_stats_for_chat[0]['dice_4']*4)+($user_stats_for_chat[0]['dice_5']*5)+($user_stats_for_chat[0]['dice_6']*6);
	$global_throw_sum = $user_global_stats[0]['dice_1']+$user_global_stats[0]['dice_2']+$user_global_stats[0]['dice_3']+$user_global_stats[0]['dice_4']+$user_global_stats[0]['dice_5']+$user_global_stats[0]['dice_6'];
	$global_points_sum = $user_global_stats[0]['dice_1']+($user_global_stats[0]['dice_2']*2)+($user_global_stats[0]['dice_3']*3)+($user_global_stats[0]['dice_4']*4)+($user_global_stats[0]['dice_5']*5)+($user_global_stats[0]['dice_6']*6);

	$user_stat_switcher_keyboard = ['inline_keyboard' => [
		[['text' => '● 🎲 Stats ●', 'callback_data' => 'get_user_dice'], ['text' => '🎯 Stats', 'callback_data' => 'get_user_darts'], ['text' => '🏀 Stats', 'callback_data' => 'get_user_basket']]
	]];

	sendMessage($chat_id, "🎲 stats for *".strtr($member['result']['user']['first_name'], $markdownify_array)."*:\n
	_In this chat_:
	*". $chat_points_sum ."* total points: _*".$user_stats_for_chat[0]['dice_6']."*_ high rolls in _*".$chat_throw_sum."*_ throws\n\t├─ _1 point_: ".$user_stats_for_chat[0]['dice_1']."\n\t├─ _2 points_: ".$user_stats_for_chat[0]['dice_2']. "\n\t├─ _3 points_: ".$user_stats_for_chat[0]['dice_3']. "\n\t├─ _4 points_: ".$user_stats_for_chat[0]['dice_4']."\n\t├─ _5 points_: ".$user_stats_for_chat[0]['dice_5']."\n\t└─ _6 points_: ".$user_stats_for_chat[0]['dice_6']."
	
	_Globally_ \(in ".count($user_chats)." chats\):
	*". $global_points_sum ."* total points: _*".$user_global_stats[0]['dice_6']."*_ high rolls in _*".$global_throw_sum."*_ throws\n\t├─ _1 point_: ".$user_global_stats[0]['dice_1']."\n\t├─ _2 points_: ".$user_global_stats[0]['dice_2']. "\n\t├─ _3 points_: ".$user_global_stats[0]['dice_3']. "\n\t├─ _4 points_: ".$user_global_stats[0]['dice_4']."\n\t├─ _5 points_: ".$user_global_stats[0]['dice_5']."\n\t└─ _6 points_: ".$user_global_stats[0]['dice_6']."
	
	Use buttons below or /mystats to get _your_ stats\!", $user_stat_switcher_keyboard);

	mysqli_free_result($user_global_stats);
	mysqli_free_result($user_stats_for_chat);
	mysqli_free_result($user_chats);
	mysqli_close($db);
}





$callback_data = explode(':', $callback_data);
switch ($callback_data[0]) {
	case 'callback_data_empty':
		break;

	case 'get_dice_throw':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM dice_stats WHERE chat_id=".$callback_chat_id." ORDER BY (dice_1+dice_2+dice_3+dice_4+dice_5+dice_6) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🎲 players by _*throws*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";

		if (count($stats) == NULL) {
			$display_string .= "_No 🎲 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['dice_1']+$stats[$key]['dice_2']+$stats[$key]['dice_3']+$stats[$key]['dice_4']+$stats[$key]['dice_5']+$stats[$key]['dice_6'];
				$points_sum[$key] = $stats[$key]['dice_1']+($stats[$key]['dice_2']*2)+($stats[$key]['dice_3']*3)+($stats[$key]['dice_4']*4)+($stats[$key]['dice_5']*5)+($stats[$key]['dice_6']*6);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
						break;
		
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['dice_6']." high rolls, ". $points_sum[$key] ." total points\n";
						break;
				}
			}
		}
		

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '● 🎲 Throws ●', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
			[['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
		]];

		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);	
		break;

	case 'get_dice_win':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM dice_stats WHERE chat_id=".$callback_chat_id." ORDER BY dice_1+(dice_2*2)+(dice_3*3)+(dice_4*4)+(dice_5*5)+(dice_6*6) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🎲 players by _*total points*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";

		if (count($stats) == NULL) {
			$display_string .= "_No 🎲 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['dice_1']+$stats[$key]['dice_2']+$stats[$key]['dice_3']+$stats[$key]['dice_4']+$stats[$key]['dice_5']+$stats[$key]['dice_6'];
				$points_sum[$key] = $stats[$key]['dice_1']+($stats[$key]['dice_2']*2)+($stats[$key]['dice_3']*3)+($stats[$key]['dice_4']*4)+($stats[$key]['dice_5']*5)+($stats[$key]['dice_6']*6);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['dice_6']." high rolls, *". $points_sum[$key] ."* total points\n";
						break;
	
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['dice_6']." high rolls, *". $points_sum[$key] ."* total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['dice_6']." high rolls, *". $points_sum[$key] ."* total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['dice_6']." high rolls, *". $points_sum[$key] ."* total points\n";
						break;
				}
			}
		}

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Throws', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
			[['text' => '● 🎲 Points ●', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
		]];

		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);
		break;

	case 'get_darts_throw':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM darts_stats WHERE chat_id=".$callback_chat_id." ORDER BY (darts_1+darts_2+darts_3+darts_4+darts_5+darts_6) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🎯 players by _*throws*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";

		if (count($stats) == NULL) {
			$display_string .= "_No 🎯 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['darts_1']+$stats[$key]['darts_2']+$stats[$key]['darts_3']+$stats[$key]['darts_4']+$stats[$key]['darts_5']+$stats[$key]['darts_6'];
				$points_sum[$key] = $stats[$key]['darts_2']+($stats[$key]['darts_3']*2)+($stats[$key]['darts_4']*3)+($stats[$key]['darts_5']*5)+($stats[$key]['darts_6']*10);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
	
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
				}
			}
		}
		

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Throws', 'callback_data' => 'get_dice_throw'], ['text' => '● 🎯 Throws ●', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
			[['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
		]];

		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);
		break;

	case 'get_darts_win':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM darts_stats WHERE chat_id=".$callback_chat_id." ORDER BY darts_2+(darts_3*2)+(darts_4*3)+(darts_5*5)+(darts_6*10) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🎯 players by _*total points*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";
		if (count($stats) == NULL) {
			$display_string .= "_No 🎯 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['darts_1']+$stats[$key]['darts_2']+$stats[$key]['darts_3']+$stats[$key]['darts_4']+$stats[$key]['darts_5']+$stats[$key]['darts_6'];
				$points_sum[$key] = $stats[$key]['darts_2']+($stats[$key]['darts_3']*2)+($stats[$key]['darts_4']*3)+($stats[$key]['darts_5']*5)+($stats[$key]['darts_6']*10);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
	
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ".$stats[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
						break;
				}
			}
		}
		

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Throws', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
			[['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '● 🎯 Points ●', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
		]];

		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);
		break;

	case 'get_basket_throw':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM basket_stats WHERE chat_id=".$callback_chat_id." ORDER BY (basket_1+basket_2+basket_3+basket_4+basket_5) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🏀 players by _*throws*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";

		if (count($stats) == NULL) {
			$display_string .= "_No 🏀 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['basket_1']+$stats[$key]['basket_2']+$stats[$key]['basket_3']+$stats[$key]['basket_4']+$stats[$key]['basket_5'];
				$hoops[$key] = $stats[$key]['basket_4']+$stats[$key]['basket_5'];
				$points_sum[$key] = $stats[$key]['basket_4']+($stats[$key]['basket_5']*2);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
	
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- *".$sum[$key]."* throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
				}
			}
		}
		

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Throws', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '● 🏀 Throws ●', 'callback_data' => 'get_basket_throw']],
			[['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '🏀 Points', 'callback_data' => 'get_basket_win']]
		]];
		
		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);
		break;

	case 'get_basket_win':
		$info = json_decode(file_get_contents($GLOBALS['api'].'/getChat?chat_id='.$callback_chat_id), TRUE);
		$title = $info['result']['title'];

		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";

		$query = mysqli_query($db, "SELECT * FROM basket_stats WHERE chat_id=".$callback_chat_id." ORDER BY basket_4+(basket_5*2) DESC LIMIT 10");
		$stats = mysqli_fetch_all($query, MYSQLI_ASSOC);

		$display_string_start = "🏆 *Top ".count($stats)."* 🏀 players by _*total points*_ for *".strtr($title, $markdownify_array)."*:\n───────────────────────\n";
		$display_string = "";

		if (count($stats) == NULL) {
			$display_string .= "_No 🏀 plays registered_";
		} else {
			foreach ($stats as $key => $value) {
				$sum[$key] = $stats[$key]['basket_1']+$stats[$key]['basket_2']+$stats[$key]['basket_3']+$stats[$key]['basket_4']+$stats[$key]['basket_5'];
				$hoops[$key] = $stats[$key]['basket_4']+$stats[$key]['basket_5'];
				$points_sum[$key] = $stats[$key]['basket_4']+($stats[$key]['basket_5']*2);
				$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$stats[$key]['user_id']), TRUE);
				$members[$key] = $member['result']['user']['first_name'];
				switch ($key) {
					case 0:
						$display_string .= "🥇*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
	
					case 1:
						$display_string .= "🥈*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
					
					case 2:
						$display_string .= "🥉*".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n───────────────────────\n";
						break;
					
					default:
						$display_string .= $key+1 ."\. *".strtr($members[$key], $markdownify_array)."* \- ".$sum[$key]." throws, ". $hoops[$key] ." hoops \(".$stats[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
						break;
				}
			}
		}
		

		$stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Throws', 'callback_data' => 'get_dice_throw'], ['text' => '🎯 Throws', 'callback_data' => 'get_darts_throw'], ['text' => '🏀 Throws', 'callback_data' => 'get_basket_throw']],
			[['text' => '🎲 Points', 'callback_data' => 'get_dice_win'], ['text' => '🎯 Points', 'callback_data' => 'get_darts_win'], ['text' => '● 🏀 Points ●', 'callback_data' => 'get_basket_win']]
		]];
		
		updateMessage($callback_chat_id, $callback_message_id, $display_string_start.$display_string, $stat_switcher_keyboard);

		mysqli_free_result($stats);
		mysqli_close($db);
		break;
	
	case 'get_user_dice':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
	
		$user_stats_for_chat = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM dice_stats WHERE chat_id=".$callback_chat_id." and user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_global_stats = mysqli_fetch_all(mysqli_query($db, "SELECT chat_id, user_id, SUM(dice_1) as dice_1, SUM(dice_2) as dice_2, SUM(dice_3) as dice_3, SUM(dice_4) as dice_4, SUM(dice_5) as dice_5, SUM(dice_6) as dice_6 FROM `dice_stats` WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_chats = mysqli_fetch_all(mysqli_query($db, "SELECT count(chat_id) as chat_count FROM dice_stats WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
	
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		$chat_throw_sum = $user_stats_for_chat[0]['dice_1']+$user_stats_for_chat[0]['dice_2']+$user_stats_for_chat[0]['dice_3']+$user_stats_for_chat[0]['dice_4']+$user_stats_for_chat[0]['dice_5']+$user_stats_for_chat[0]['dice_6'];
		$chat_points_sum = $user_stats_for_chat[0]['dice_1']+($user_stats_for_chat[0]['dice_2']*2)+($user_stats_for_chat[0]['dice_3']*3)+($user_stats_for_chat[0]['dice_4']*4)+($user_stats_for_chat[0]['dice_5']*5)+($user_stats_for_chat[0]['dice_6']*6);
		$global_throw_sum = $user_global_stats[0]['dice_1']+$user_global_stats[0]['dice_2']+$user_global_stats[0]['dice_3']+$user_global_stats[0]['dice_4']+$user_global_stats[0]['dice_5']+$user_global_stats[0]['dice_6'];
		$global_points_sum = $user_global_stats[0]['dice_1']+($user_global_stats[0]['dice_2']*2)+($user_global_stats[0]['dice_3']*3)+($user_global_stats[0]['dice_4']*4)+($user_global_stats[0]['dice_5']*5)+($user_global_stats[0]['dice_6']*6);
	
		$user_stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '● 🎲 Stats ●', 'callback_data' => 'get_user_dice'], ['text' => '🎯 Stats', 'callback_data' => 'get_user_darts'], ['text' => '🏀 Stats', 'callback_data' => 'get_user_basket']]
		]];
	
		updateMessage($callback_chat_id, $callback_message_id, "🎲 stats for *".strtr($member['result']['user']['first_name'], $markdownify_array)."*:\n
		_In this chat_:
		*". $chat_points_sum ."* total points: _*".$user_stats_for_chat[0]['dice_6']."*_ high rolls in _*".$chat_throw_sum."*_ throws\n\t├─ _1 point_: ".$user_stats_for_chat[0]['dice_1']."\n\t├─ _2 points_: ".$user_stats_for_chat[0]['dice_2']. "\n\t├─ _3 points_: ".$user_stats_for_chat[0]['dice_3']. "\n\t├─ _4 points_: ".$user_stats_for_chat[0]['dice_4']."\n\t├─ _5 points_: ".$user_stats_for_chat[0]['dice_5']."\n\t└─ _6 points_: ".$user_stats_for_chat[0]['dice_6']."
		
		_Globally_ \(in ".$user_chats[0]['chat_count']." chats\):
		*". $global_points_sum ."* total points: _*".$user_global_stats[0]['dice_6']."*_ high rolls in _*".$global_throw_sum."*_ throws\n\t├─ _1 point_: ".$user_global_stats[0]['dice_1']."\n\t├─ _2 points_: ".$user_global_stats[0]['dice_2']. "\n\t├─ _3 points_: ".$user_global_stats[0]['dice_3']. "\n\t├─ _4 points_: ".$user_global_stats[0]['dice_4']."\n\t├─ _5 points_: ".$user_global_stats[0]['dice_5']."\n\t└─ _6 points_: ".$user_global_stats[0]['dice_6']."
		
		Use buttons below or /mystats to get _your_ stats\!", $user_stat_switcher_keyboard);
	
		mysqli_free_result($user_global_stats);
		mysqli_free_result($user_stats_for_chat);
		mysqli_free_result($user_chats);
		mysqli_close($db);
		break;

	case 'get_user_darts':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
	
		$user_stats_for_chat = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM darts_stats WHERE chat_id=".$callback_chat_id." and user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_global_stats = mysqli_fetch_all(mysqli_query($db, "SELECT chat_id, user_id, SUM(darts_1) as darts_1, SUM(darts_2) as darts_2, SUM(darts_3) as darts_3, SUM(darts_4) as darts_4, SUM(darts_5) as darts_5, SUM(darts_6) as darts_6 FROM `darts_stats` WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_chats = mysqli_fetch_all(mysqli_query($db, "SELECT count(chat_id) as chat_count FROM darts_stats WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
	
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		$chat_throw_sum = $user_stats_for_chat[0]['darts_1']+$user_stats_for_chat[0]['darts_2']+$user_stats_for_chat[0]['darts_3']+$user_stats_for_chat[0]['darts_4']+$user_stats_for_chat[0]['darts_5']+$user_stats_for_chat[0]['darts_6'];
		$chat_points_sum = $user_stats_for_chat[0]['darts_2']+($user_stats_for_chat[0]['darts_3']*2)+($user_stats_for_chat[0]['darts_4']*3)+($user_stats_for_chat[0]['darts_5']*5)+($user_stats_for_chat[0]['darts_6']*10);
		$global_throw_sum = $user_global_stats[0]['darts_1']+$user_global_stats[0]['darts_2']+$user_global_stats[0]['darts_3']+$user_global_stats[0]['darts_4']+$user_global_stats[0]['darts_5']+$user_global_stats[0]['darts_6'];
		$global_points_sum = $user_global_stats[0]['darts_2']+($user_global_stats[0]['darts_3']*2)+($user_global_stats[0]['darts_4']*3)+($user_global_stats[0]['darts_5']*5)+($user_global_stats[0]['darts_6']*10);

		$user_stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Stats', 'callback_data' => 'get_user_dice'], ['text' => '● 🎯 Stats ●', 'callback_data' => 'get_user_darts'], ['text' => '🏀 Stats', 'callback_data' => 'get_user_basket']]
		]];
	
		updateMessage($callback_chat_id, $callback_message_id, "🎯 stats for *".strtr($member['result']['user']['first_name'], $markdownify_array)."*:\n
		_In this chat_:
		*". $chat_points_sum ."* total points: _*".$user_stats_for_chat[0]['darts_6']."*_ bullseyes in _*".$chat_throw_sum."*_ throws\n\t├─ _misses_: ".$user_stats_for_chat[0]['darts_1']."\n\t├─ _1 points_: ".$user_stats_for_chat[0]['darts_2']. "\n\t├─ _2 points_: ".$user_stats_for_chat[0]['darts_3']. "\n\t├─ _3 points_: ".$user_stats_for_chat[0]['darts_4']."\n\t├─ _5 points_: ".$user_stats_for_chat[0]['darts_5']."\n\t└─ _Bullseyes \(10 points\)_: ".$user_stats_for_chat[0]['darts_6']."
		
		_Globally_ \(in ".$user_chats[0]['chat_count']." chats\):
		*". $global_points_sum ."* total points: _*".$user_global_stats[0]['darts_6']."*_ bullseyes in _*".$global_throw_sum."*_ throws\n\t├─ _misses_: ".$user_global_stats[0]['darts_1']."\n\t├─ _1 points_: ".$user_global_stats[0]['darts_2']. "\n\t├─ _2 points_: ".$user_global_stats[0]['darts_3']. "\n\t├─ _3 points_: ".$user_global_stats[0]['darts_4']."\n\t├─ _5 points_: ".$user_global_stats[0]['darts_5']."\n\t└─ _Bullseyes \(10 points\)_: ".$user_global_stats[0]['darts_6']."
		
		Use buttons below or /mystats to get _your_ stats\!", $user_stat_switcher_keyboard);
	
		mysqli_free_result($user_global_stats);
		mysqli_free_result($user_stats_for_chat);
		mysqli_free_result($user_chats);
		mysqli_close($db);
		break;

	case 'get_user_basket':
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
	
		$user_stats_for_chat = mysqli_fetch_all(mysqli_query($db, "SELECT * FROM basket_stats WHERE chat_id=".$callback_chat_id." and user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_global_stats = mysqli_fetch_all(mysqli_query($db, "SELECT chat_id, user_id, SUM(basket_1) as basket_1, SUM(basket_2) as basket_2, SUM(basket_3) as basket_3, SUM(basket_4) as basket_4, SUM(basket_5) as basket_5 FROM `basket_stats` WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
		$user_chats = mysqli_fetch_all(mysqli_query($db, "SELECT count(chat_id) as chat_count FROM basket_stats WHERE user_id=".$callback_user_id), MYSQLI_ASSOC);
	
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		$chat_throw_sum = $user_stats_for_chat[0]['basket_1']+$user_stats_for_chat[0]['basket_2']+$user_stats_for_chat[0]['basket_3']+$user_stats_for_chat[0]['basket_4']+$user_stats_for_chat[0]['basket_5'];
		$chat_hoops_sum = $user_stats_for_chat[0]['basket_4']+$user_stats_for_chat[0]['basket_5'];
		$chat_misses = $user_stats_for_chat[0]['basket_1']+$user_stats_for_chat[0]['basket_2'];
		$chat_points_sum = $user_stats_for_chat[0]['basket_4']+($user_stats_for_chat[0]['basket_5']*2);
		$global_throw_sum = $user_global_stats[0]['basket_1']+$user_global_stats[0]['basket_2']+$user_global_stats[0]['basket_3']+$user_global_stats[0]['basket_4']+$user_global_stats[0]['basket_5'];
		$global_hoops_sum = $user_global_stats[0]['basket_4']+$user_global_stats[0]['basket_5'];
		$global_misses = $user_global_stats[0]['basket_1']+$user_global_stats[0]['basket_2'];
		$global_points_sum = $user_global_stats[0]['basket_4']+($user_global_stats[0]['basket_5']*2);

		$user_stat_switcher_keyboard = ['inline_keyboard' => [
			[['text' => '🎲 Stats', 'callback_data' => 'get_user_dice'], ['text' => '🎯 Stats', 'callback_data' => 'get_user_darts'], ['text' => '● 🏀 Stats ●', 'callback_data' => 'get_user_basket']]
		]];
	
		updateMessage($callback_chat_id, $callback_message_id, "🏀 stats for *".strtr($member['result']['user']['first_name'], $markdownify_array)."*:\n
		_In this chat_:
		*". $chat_points_sum ."* total points: _*".$chat_hoops_sum."*_ hoops in _*".$chat_throw_sum."*_ throws\n\t├─ _misses_: ".$chat_misses."\n\t├─ _stuck balls_: ".$user_stats_for_chat[0]['basket_3']."\n\t├─ _ring hoops \(1 point\)_: ".$user_stats_for_chat[0]['basket_4']."\n\t└─ _clean hoops \(2 points\)_: ".$user_stats_for_chat[0]['basket_5']."
		
		_Globally_ \(in ".$user_chats[0]['chat_count']." chats\):
		*". $global_points_sum ."* total points: _*".$global_hoops_sum."*_ hoops in _*".$global_throw_sum."*_ throws\n\t├─ _misses_: ".$global_misses."\n\t├─ _stuck balls_: ".$user_global_stats[0]['basket_3']."\n\t├─ _ring hoops \(1 point\)_: ".$user_global_stats[0]['basket_4']."\n\t└─ _clean hoops \(2 points\)_: ".$user_global_stats[0]['basket_5']."
		
		Use buttons below or /mystats to get _your_ stats\!", $user_stat_switcher_keyboard);
	
		mysqli_free_result($user_global_stats);
		mysqli_free_result($user_stats_for_chat);
		mysqli_free_result($user_chats);
		mysqli_close($db);
		break;











	case 'init_dice_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set type='dice' where chat_id=".$callback_chat_id);

			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_dice_tournament:5'], ['text' => '10', 'callback_data' => 'start_dice_tournament:10'], ['text' => '20', 'callback_data' => 'start_dice_tournament:20'], ['text' => '30', 'callback_data' => 'start_dice_tournament:30']]
			]];

			updateMessage($callback_chat_id, $callback_message_id, "Cool, a 🎲 tournament\!\n\nNow pick an amount of throws:", $tournament_keyboard);
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '🎲', 'callback_data' => 'init_dice_tournament'], ['text' => '🎯', 'callback_data' => 'init_darts_tournament'], ['text' => '🏀', 'callback_data' => 'init_basket_tournament']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;

	case 'init_darts_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set type='darts' where chat_id=".$callback_chat_id);

			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_darts_tournament:5'], ['text' => '10', 'callback_data' => 'start_darts_tournament:10'], ['text' => '20', 'callback_data' => 'start_darts_tournament:20'], ['text' => '30', 'callback_data' => 'start_darts_tournament:30']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, "Cool, a 🎯 tournament\!\n\nNow pick an amount of throws:", $tournament_keyboard);
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '🎲', 'callback_data' => 'init_darts_tournament'], ['text' => '🎯', 'callback_data' => 'init_darts_tournament'], ['text' => '🏀', 'callback_data' => 'init_basket_tournament']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;

	case 'init_basket_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set type='basket' where chat_id=".$callback_chat_id);

			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_basket_tournament:5'], ['text' => '10', 'callback_data' => 'start_basket_tournament:10'], ['text' => '20', 'callback_data' => 'start_basket_tournament:20'], ['text' => '30', 'callback_data' => 'start_basket_tournament:30']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, "Cool, a 🏀 tournament\!\n\nNow pick an amount of throws:", $tournament_keyboard);
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '🎲', 'callback_data' => 'init_basket_tournament'], ['text' => '🎯', 'callback_data' => 'init_darts_tournament'], ['text' => '🏀', 'callback_data' => 'init_basket_tournament']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;
	
	case 'start_dice_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set count=".$callback_data[1].", status='ongoing' where chat_id=".$callback_chat_id);
			mysqli_query($db, "delete from dice_tournament_stats where chat_id=".$callback_chat_id);
			
			updateMessage($callback_chat_id, $callback_message_id, "Great\! You have started a 🎲 tournament with ".$callback_data[1]." throws per player and it is now live\!\n\nTo stop a tournament, type /stop");
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_dice_tournament:5'], ['text' => '10', 'callback_data' => 'start_dice_tournament:10'], ['text' => '20', 'callback_data' => 'start_dice_tournament:20'], ['text' => '30', 'callback_data' => 'start_dice_tournament:30']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;

	case 'start_darts_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set count=".$callback_data[1].", status='ongoing' where chat_id=".$callback_chat_id);
			mysqli_query($db, "delete from darts_tournament_stats where chat_id=".$callback_chat_id);
			
			updateMessage($callback_chat_id, $callback_message_id, "Great\! You have started a 🎯 tournament with ".$callback_data[1]." throws per player and it is now live\!\n\nTo stop a tournament, type /stop");
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_darts_tournament:5'], ['text' => '10', 'callback_data' => 'start_darts_tournament:10'], ['text' => '20', 'callback_data' => 'start_darts_tournament:20'], ['text' => '30', 'callback_data' => 'start_darts_tournament:30']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;

	case 'start_basket_tournament':
		$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$callback_chat_id.'&user_id='.$callback_user_id), TRUE);
	
		if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
			$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
			mysqli_set_charset($db, 'utf8mb4');
			mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
				else echo "MySQL connect successful.\n";

			mysqli_query($db, "update tournament_status set count=".$callback_data[1].", status='ongoing' where chat_id=".$callback_chat_id);
			mysqli_query($db, "delete from basket_tournament_stats where chat_id=".$callback_chat_id);
			
			updateMessage($callback_chat_id, $callback_message_id, "Great\! You have started a 🏀 tournament with ".$callback_data[1]." throws per player and it is now live\!\n\nTo stop a tournament, type /stop");
		} else {
			$tournament_keyboard = ['inline_keyboard' => [
				[['text' => '5', 'callback_data' => 'start_basket_tournament:5'], ['text' => '10', 'callback_data' => 'start_basket_tournament:10'], ['text' => '20', 'callback_data' => 'start_basket_tournament:20'], ['text' => '30', 'callback_data' => 'start_basket_tournament:30']]
			]];
			updateMessage($callback_chat_id, $callback_message_id, $callback_message_text, $tournament_keyboard, "This action is for admins only.");
		}
		
		break;
		
}


if ($message == '/stop' || $message == '/stop@dicestatbot') {
	$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$user_id), TRUE);

	if ($member['result']['status'] == 'creator' || $member['result']['status'] == 'administrator') {
		$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
		mysqli_set_charset($db, 'utf8mb4');
		mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
		if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
			else echo "MySQL connect successful.\n";
		
		$tournament = mysqli_fetch_all(mysqli_query($db, "select status, type, count from tournament_status where chat_id=".$chat_id), MYSQLI_ASSOC);

		if ($tournament[0]['status'] == 'ongoing') {
			switch ($tournament[0]['type']) {
				case 'dice':
					$display_tournament_result_start = "_Horray\! The tournament has concluded\!_\n\nOut of ".$tournament[0]['count']." 🎲 throws, our winners are:\n";
					$display_tournament_result = "";
	
					$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, dice_1, dice_2, dice_3, dice_4, dice_5, dice_6 from dice_tournament_stats where chat_id=".$chat_id." order by dice_1+(dice_2*2)+(dice_3*3)+(dice_4*4)+(dice_5*5)+(dice_6*6) DESC LIMIT 3"), MYSQLI_ASSOC);

					if (is_null($tournament_winners[0])) {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
						sendMessage($chat_id, "_No plays were registered on a tournament\._");
						break;
					} else {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
					}

					foreach ($tournament_winners as $key => $value) {
						$sum[$key] = $tournament_winners[$key]['dice_1']+$tournament_winners[$key]['dice_2']+$tournament_winners[$key]['dice_3']+$tournament_winners[$key]['dice_4']+$tournament_winners[$key]['dice_5']+$tournament_winners[$key]['dice_6'];
						$points_sum[$key] = $tournament_winners[$key]['dice_1']+($tournament_winners[$key]['dice_2'])*2+($tournament_winners[$key]['dice_3']*3)+($tournament_winners[$key]['dice_4']*4)+($tournament_winners[$key]['dice_5']*5)+($tournament_winners[$key]['dice_6']*6);
						$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
						$members[$key] = $member['result']['user']['first_name'];
						switch ($key) {
							case 0:
								$display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\!\n";
								break;
			
							case 1:
								$display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\n";
								break;
							
							case 2:
								$display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\n";
								break;
						}
					}

					
	
					sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
					break;
				
				case 'darts':
					$display_tournament_result_start = "_Horray\! The tournament has concluded\!_\n\nOut of ".$tournament[0]['count']." 🎯 throws, our winners are:\n";
					$display_tournament_result = "";
	
					$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, darts_1, darts_2, darts_3, darts_4, darts_5, darts_6 from darts_tournament_stats where chat_id=".$chat_id." order by darts_2+(darts_3*2)+(darts_4*3)+(darts_5*5)+(darts_6*10) DESC LIMIT 3"), MYSQLI_ASSOC);

					if (is_null($tournament_winners[0])) {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
						sendMessage($chat_id, "_No plays were registered on a tournament\._");
						break;
					} else {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
					}
	
					foreach ($tournament_winners as $key => $value) {
						$sum[$key] = $tournament_winners[$key]['darts_1']+$tournament_winners[$key]['darts_2']+$tournament_winners[$key]['darts_3']+$tournament_winners[$key]['darts_4']+$tournament_winners[$key]['darts_5']+$tournament_winners[$key]['darts_6'];
						$points_sum[$key] = $tournament_winners[$key]['darts_2']+($tournament_winners[$key]['darts_3']*2)+($tournament_winners[$key]['darts_4']*3)+($tournament_winners[$key]['darts_5']*5)+($tournament_winners[$key]['darts_6']*10);
						$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
						$members[$key] = $member['result']['user']['first_name'];
						switch ($key) {
							case 0:
								$display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
								break;
			
							case 1:
								$display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
								break;
							
							case 2:
								$display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
								break;
						}
					}

					
	
					sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
					break;
				
				case 'basket':
					$display_tournament_result_start = "_Horray\! The tournament has concluded\!_\n\nOut of ".$tournament[0]['count']." 🏀 throws, our winners are:\n";
					$display_tournament_result = "";
	
					$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, basket_1, basket_2, basket_3, basket_4, basket_5 from basket_tournament_stats where chat_id=".$chat_id." order by basket_4+(basket_5*2) DESC LIMIT 3"), MYSQLI_ASSOC);
					
					if (is_null($tournament_winners[0])) {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
						sendMessage($chat_id, "_No plays were registered on a tournament\._");
						break;
					} else {
						mysqli_query($db, "update tournament_status set status='ended' where chat_id=".$chat_id);
					}
	
					foreach ($tournament_winners as $key => $value) {
						$sum[$key] = $tournament_winners[$key]['basket_1']+$tournament_winners[$key]['basket_2']+$tournament_winners[$key]['basket_3']+$tournament_winners[$key]['basket_4']+$tournament_winners[$key]['basket_5'];
						$hoops[$key] = $tournament_winners[$key]['basket_4']+$tournament_winners[$key]['basket_5'];
						$points_sum[$key] = $tournament_winners[$key]['basket_4']+($tournament_winners[$key]['basket_5']*2);
						$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
						$members[$key] = $member['result']['user']['first_name'];
						switch ($key) {
							case 0:
								$display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
								break;
			
							case 1:
								$display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
								break;
							
							case 2:
								$display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
								break;
						}
					}

					
	
					sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
					break;
				
			}		
		} else {
			sendMessage($chat_id, "_The tournament is either already over, or never been started\._");
		}
			
	} else {
		sendReply($chat_id, "_Sorry, only admins can stop the tournament\._", $message_id);
	}
}

if ($message == '/last_tournament' || $message == '/last_tournament@dicestatbot') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";
	
	$tournament = mysqli_fetch_all(mysqli_query($db, "select status, type, count, last_ran from tournament_status where chat_id=".$chat_id), MYSQLI_ASSOC);

	$display_tournament_result = "";

	if ($tournament[0]['status'] == 'ongoing') {
		sendMessage($chat_id, "_There is an ongoing tournament already\! Wait for it to be ended by admin._");
	} elseif ($tournament[0]['status'] == 'never_ran') {
		sendMessage($chat_id, "_There were no tournaments in this chat\.\n\nAdmins can type /tournament to initiate a tournament\._");
	} else {
		switch ($tournament[0]['type']) {
			case 'dice':
				$display_tournament_result_start = "Last tournament was on ".strtr($tournament[0]['last_ran'], $markdownify_array)."\n\nOut of ".$tournament[0]['count']." 🎲 throws, our winners were:\n";
	
				$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, dice_1, dice_2, dice_3, dice_4, dice_5, dice_6 from dice_tournament_stats where chat_id=".$chat_id." order by dice_1+(dice_2*2)+(dice_3*3)+(dice_4*4)+(dice_5*5)+(dice_6*6) DESC LIMIT 3"), MYSQLI_ASSOC);

				foreach ($tournament_winners as $key => $value) {
					$sum[$key] = $tournament_winners[$key]['dice_1']+$tournament_winners[$key]['dice_2']+$tournament_winners[$key]['dice_3']+$tournament_winners[$key]['dice_4']+$tournament_winners[$key]['dice_5']+$tournament_winners[$key]['dice_6'];
					$points_sum[$key] = $tournament_winners[$key]['dice_1']+($tournament_winners[$key]['dice_2'])*2+($tournament_winners[$key]['dice_3']*3)+($tournament_winners[$key]['dice_4']*4)+($tournament_winners[$key]['dice_5']*5)+($tournament_winners[$key]['dice_6']*6);
					$member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
					$members[$key] = $member['result']['user']['first_name'];
					switch ($key) {
						case 0:
							$display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\!\n";
							break;
		
						case 1:
							$display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\n";
							break;
						
						case 2:
							$display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['dice_6']." high rolls and *". $points_sum[$key] ."* total points\n";
							break;
					}
				}
	
				sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
				break;
			
			case 'darts':
				$display_tournament_result_start = "Last tournament was on ".strtr($tournament[0]['last_ran'], $markdownify_array)."\n\nOut of ".$tournament[0]['count']." 🎯 throws, our winners were:\n";
	
				$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, darts_1, darts_2, darts_3, darts_4, darts_5, darts_6 from darts_tournament_stats where chat_id=".$chat_id." order by darts_2+(darts_3*2)+(darts_4*3)+(darts_5*5)+(darts_6*10) DESC LIMIT 3"), MYSQLI_ASSOC);
	
				foreach ($tournament_winners as $key => $value) {
                    $sum[$key] = $tournament_winners[$key]['darts_1']+$tournament_winners[$key]['darts_2']+$tournament_winners[$key]['darts_3']+$tournament_winners[$key]['darts_4']+$tournament_winners[$key]['darts_5']+$tournament_winners[$key]['darts_6'];
                    $points_sum[$key] = $tournament_winners[$key]['darts_2']+($tournament_winners[$key]['darts_3']*2)+($tournament_winners[$key]['darts_4']*3)+($tournament_winners[$key]['darts_5']*5)+($tournament_winners[$key]['darts_6']*10);
                    $member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
                    $members[$key] = $member['result']['user']['first_name'];
                    switch ($key) {
                        case 0:
                            $display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
                            break;
        
                        case 1:
                            $display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
                            break;
                        
                        case 2:
                            $display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ".$tournament_winners[$key]['darts_6']." bullseyes, *". $points_sum[$key] ."* total points\n";
                            break;
                    }
                }
	
				sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
				break;
			
			case 'basket':
				$display_tournament_result_start = "Last tournament was on ".strtr($tournament[0]['last_ran'], $markdownify_array)."\n\nOut of ".$tournament[0]['count']." 🏀 throws, our winners were:\n";
	
				$tournament_winners = mysqli_fetch_all(mysqli_query($db, "select user_id, basket_1, basket_2, basket_3, basket_4, basket_5 from basket_tournament_stats where chat_id=".$chat_id." order by basket_4+(basket_5*2) DESC LIMIT 3"), MYSQLI_ASSOC);
	
				foreach ($tournament_winners as $key => $value) {
                    $sum[$key] = $tournament_winners[$key]['basket_1']+$tournament_winners[$key]['basket_2']+$tournament_winners[$key]['basket_3']+$tournament_winners[$key]['basket_4']+$tournament_winners[$key]['basket_5'];
                    $hoops[$key] = $tournament_winners[$key]['basket_4']+$tournament_winners[$key]['basket_5'];
                    $points_sum[$key] = $tournament_winners[$key]['basket_4']+($tournament_winners[$key]['basket_5']*2);
                    $member = json_decode(file_get_contents($GLOBALS['api'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$tournament_winners[$key]['user_id']), TRUE);
                    $members[$key] = $member['result']['user']['first_name'];
                    switch ($key) {
                        case 0:
                            $display_tournament_result .= "🥇*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
                            break;
        
                        case 1:
                            $display_tournament_result .= "🥈*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
                            break;
                        
                        case 2:
                            $display_tournament_result .= "🥉*".strtr($members[$key], $markdownify_array)."* with ". $hoops[$key] ." hoops \(".$tournament_winners[$key]['basket_5']." clean\), *". $points_sum[$key] ."* total points\n";
                            break;
                    }
                }
	
				sendMessage($chat_id, $display_tournament_result_start.$display_tournament_result."\nCongratulations\!");
				break;
			
		}
	}
}



if (is_int(stripos($message, '/notify ')) && $chat_id == '197416875') {
	$db = mysqli_connect($db_host, $db_username, $db_pass, $db_schema);
	mysqli_set_charset($db, 'utf8mb4');
	mysqli_query($db, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	if (mysqli_connect_errno()) error_log("Failed to connect to MySQL: " . mysqli_connect_error());
		else echo "MySQL connect successful.\n";
	
	$query = mysqli_query($db, 'select distinct chat_id from (select chat_id from tournament_status) notify');
	while ($sql = mysqli_fetch_object($query)) {
		$owners_list[] = $sql->chat_id;
	}

	$notify = substr($message, 8);	
	foreach ($owners_list as $id) {
		sendMessage($id, strtr($notify, $markdownify_array), NULL);
	}
	mysqli_free_result($sql);
	mysqli_close($db);
}






//----------------------------------------------------------------------------------------------------------------------------------//

//отправка форматированного сообщения
function sendMessage($chat_id, $message, $inline_keyboard = NULL) {
	if ($inline_keyboard === NULL) {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2');
	} else {
		file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&reply_markup='.json_encode($inline_keyboard).'&parse_mode=MarkdownV2');
	}
}

//редактирование сообщения
function updateMessage($chat_id, $message_id, $new_message, $inline_keyboard = NULL, $alert_text = NULL)
{
	if ($inline_keyboard === NULL) {
		file_get_contents($GLOBALS['api'].'/editMessageText?chat_id='.$chat_id.'&message_id='.$message_id.'&text='.urlencode($new_message).'&parse_mode=MarkdownV2');
	} else {
		file_get_contents($GLOBALS['api'].'/editMessageText?chat_id='.$chat_id.'&message_id='.$message_id.'&text='.urlencode($new_message).'&reply_markup='.json_encode($inline_keyboard).'&parse_mode=MarkdownV2');
		if ($alert_text === NULL) {
			file_get_contents($GLOBALS['api'].'/answerCallbackQuery?callback_query_id='.$GLOBALS['callback_id']);
		} else {
			file_get_contents($GLOBALS['api'].'/answerCallbackQuery?callback_query_id='.$GLOBALS['callback_id'].'&text='.urlencode($alert_text));
		}
	}
}

//отправка приветствия
function sendReply($chat_id, $message, $message_id_to_reply) {
	file_get_contents($GLOBALS['api'].'/sendMessage?chat_id='.$chat_id.'&text='.urlencode($message).'&parse_mode=MarkdownV2'.'&reply_to_message_id='.$message_id_to_reply);
}

//удаление служебного сообщения
function deleteMessage($chat_id, $message_id) {
	file_get_contents($GLOBALS['api'].'/deleteMessage?chat_id='.$chat_id.'&message_id='.$message_id);
}

//покидание чата
function leaveChat($chat_id) {
	file_get_contents($GLOBALS['api'].'/leaveChat?chat_id='.$chat_id);
}

mysqli_close($db);
echo "End script."
?>
