<?php

function getMessage($data) {
	$len = strlen($data) + 9;
	$p1 = pack('c', $len) . "\x00\x00\x00";
	$p2 = $p1;
	$p3 = "\xb1\x02\x00\x00";
	$p4 = $data;
	$p5 = "\x00";
	$msg = $p1 . $p2 . $p3 . $p4 . $p5;
	return $msg;
}

if (!$argv[1]) {
	printf("Usage: php %s https://www.douyu.com/room_id\n", $argv[0]);
	exit;
}
$html = file_get_contents($argv[1]);
if (!$html) {
	exit;
}
preg_match('/"server_config":"([^"]+)"/', $html, $match);
$json = json_decode(urldecode($match[1]), true);
$k = array_rand($json);
$server = $json[$k];
$host = $server['ip'];
$port = $server['port'];
preg_match('#/(\d+)$#', $argv[1], $match);
$room_id = $match[1];

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, $host, $port);

$rt = time();
$dev_id = strtoupper(md5(php_uname()));
$salt = '7oE9nPEG9xXV69phU31FYCLUagKeYtsF';
$vk = md5($rt . $salt . $dev_id);
$data = "type@=loginreq/username@=/ct@=0/password@=/roomid@={$room_id}/devid@={$dev_id}/rt@={$rt}/vk@={$vk}/ver@=20150929/";
$msg = getMessage($data);
socket_send($socket, $msg, strlen($msg), 0);
$f1 = $f2 = false;
while ($n = socket_recv($socket, $recv, 4000, 0)) {
	if (preg_match('#type@=loginres/.+username@=(.+)/nickname#', $recv, $match)) {
		$username = $match[1];
	}
	if (strpos($recv, 'msgrepeaterlist') !== false) {
		preg_match('#ip@AA=(.+?)@ASport@AA=(\d+)@AS#', $recv, $match);
		$danmaku_host = $match[1];
		$danmaku_port = $match[2];
		$f1 = true;
	}
	if (strpos($recv, 'setmsggroup') !== false) {
		preg_match('#gid@=(\d+)#', $recv, $match);
		$gid = $match[1];
		$f2 = true;
	}
	if ($f1 && $f2) {
		break;
	}
}
socket_close($socket);
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, $danmaku_host, $danmaku_port);
$password = rand(10000000, 99999999);
$data = "type@=loginreq/username@={$username}/password@={$password}/roomid@={$room_id}/";
$msg = getMessage($data);
socket_send($socket, $msg, strlen($msg), 0);
socket_recv($socket, $recv, 4000, 0);
$data = "type@=joingroup/rid@={$room_id}/gid@={$gid}/";
$msg = getMessage($data);
socket_send($socket, $msg, strlen($msg), 0);
socket_recv($socket, $recv, 4000, 0);
$rt = time();
while ($n = socket_recv($socket, $recv, 4000, 0)) {
	$t = time();
	if (preg_match('#nn@=(.+)/txt@=(.+)/cid#', $recv, $match)) {
		printf("%s\t%s\n", $match[1], $match[2]);
	}
	if ($t > $rt + 40) {
		$rt = time();
		$data = "type@=keeplive/tick@={$rt}/vbw@=0/";
		$msg = getMessage($data);
		socket_send($socket, $msg, strlen($msg), 0);
	}
}
