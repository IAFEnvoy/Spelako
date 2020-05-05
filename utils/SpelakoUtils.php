<?php
global $stream_opts;
$GLOBALS['stream_opts'] = [
	'ssl' => [
		'verify_peer' => false,
		'verify_peer_name' => false
	]
];
date_default_timezone_set('PRC');

// 文件操作模块
function rfile($path) {
	if(file_exists($path))
		$f = file_get_contents($path);
	else
		$f = NULL;
	return $f;
}

function wfile($path, $contents) {
	if(!file_exists($path)){
		$spl = explode('/', $path);
		$dir = implode('/', array_slice($spl, 0, count($spl) - 1));
		//echo '| dir: '.$dir.', full: '.$path.' |';
		mkdir($dir, NULL, true);
		touch($path);
	};
	file_put_contents($path, $contents);
}

function format_size($byte) {
	$a = array('字节', 'KB', 'MB', 'GB', 'TB', 'PB');
	$pos = 0;
	while ($byte >= 1024) {
		$byte /= 1024;
		$pos ++;
	}
	return round($byte, 2).' '.$a[$pos];
}

function fsize($path) {
	if(file_exists($path)) {
		$size = filesize($path);
		return format_size($size);
	}
	else return '文件不存在';
}

function dirToArray($dir) {
  
	$result = array();
 
	$cdir = scandir($dir);
	foreach ($cdir as $key => $value)
	{
		if (!in_array($value,array(".","..")))
		{
			if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
			{
				$result[$value] = dirToArray($dir . DIRECTORY_SEPARATOR . $value);
			}
			else
			{
				$result[] = $value;
			}
		}
	}
	return $result;
}

function info_item($param, $i = 0)
{
    switch (gettype($param)) {
        case 'array':
			$space = '';
			$num = $i;
			while ($num) {
				$space .= '	';
				$num--;
			}
			echo ''.count($param).' 个对象:'.PHP_EOL;
			foreach ($param as $key => $item) {
				echo $space;
				if(gettype($key) == 'string') {
					echo $key.'/ - ';
				}
				else if($key > 4) {
					echo '...';
					break;
				}
				info_item($item, $i+1);
			}
			echo $space;
            break;
        default:
            echo $param;
            break;
    }
    echo PHP_EOL;
}

function ddump($path) {
	ob_start();
	info_item($path);
	$contents = ob_get_contents();
	ob_end_clean();
	$contents = preg_replace('@(\n\s+(?=\n))@', '', $contents);
	$contents = preg_replace('@\n@', '\n| ', $contents);
	echo $contents;
}

function dsize($path, $noformat = false) {
	if(is_dir($path)) {
		$size = 0;
		$handle = opendir($path);
		while (($item = readdir($handle)) !== false) {
			if ($item == '.' || $item == '..') continue;
			$_path = $path . '/' . $item;
			if (is_file($_path)) $size += filesize($_path);
			if (is_dir($_path)) $size += dsize($_path, true);
		}
		closedir($handle);
		return $noformat? $size : format_size($size);
	}
	else return '目录不存在';
}

function isOutdated($path, $timeout) {
	if(file_exists($path)) {
		return ((time() - filemtime($path)) > $timeout);
	}
	else return true;
}

// 黑名单模块
function getBlacklist($isGroup = false){
	$contents = rfile($isGroup ? 'saves/blacklist/group.txt' : 'saves/blacklist/user.txt');
	$list = explode(PHP_EOL, $contents);
	$list = array_filter($list);
	return $list;
}
function saveBlacklist($list, $isGroup = false){
	$contents = implode(PHP_EOL, array_filter($list));
	wfile($isGroup ? 'saves/blacklist/group.txt' : 'saves/blacklist/user.txt', $contents);
}
function isBlacklisted(string $number, bool $isGroup = false){
	return in_array($number, getBlacklist($isGroup));
}
function blacklistAdd(string $number, bool $isGroup = false){
	$list = getBlacklist($isGroup);
	if(in_array($number, $list)){
		return false;
	}
	else {
		array_push($list, $number);
		saveBlacklist($list, $isGroup);
		return true;
	}
}
function blacklistRemove(string $number, bool $isGroup = false){
	$list = getBlacklist($isGroup);
	if(in_array($number, $list)){
		$list = array_diff($list, [$number]);
		saveBlacklist($list, $isGroup);
		return true;
	}
	else {
		return false;
	}
}

// 冷却模块
function getCooldowns(){
	$contents = rfile('cache/cooldown.json');
	$arr = json_decode($contents, true);
	return $arr;
}
function saveCooldowns($list){
	wfile('cache/cooldown.json', json_encode($list));
}
function userExecute(string $user){
	$cd = getCooldowns();
	$cd[$user] = time();
	saveCooldowns($cd);
}
function getAvailability(string $user){
	$cd = getCooldowns();
	return (time() - $cd[$user] > 6);
}

function toDate($unix, $second = false) {
	if($unix == null)
		return '未知';
	else
		return date('Y-m-d H:i', $unix / ($second ? 1 : 1000)).' CST';
}

// 纠错模块
function similarCommand($findBy, array $cmdList) {
	$findBy = substr($findBy, 1);
	foreach ($cmdList as $v) {
		similar_text($findBy, $v, $percent);
		$sCmdList[$v] = $percent;
	}
	$bestValue = max($sCmdList);
	$bestMatch = array_search($bestValue, $sCmdList);
	return ($bestValue > 70 && $bestValue != 100) ? $bestMatch : false;
}
?>