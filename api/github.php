<?php

error_reporting(0);
set_time_limit(300);
require '../source/function/function_core.php';

if(empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_GITHUB_DELIVERY']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']) || substr($_SERVER['HTTP_USER_AGENT'], 0, 15) != 'GitHub Hookshot' || !in_array($_SERVER['HTTP_X_GITHUB_EVENT'], array('ping', 'push'))) {
	send_http_status(403);
	exit('Access Denied');
}

try {
	$input = file_get_contents('php://input');
	$input = json_decode($input, true);
} catch (Exception $e) {
	send_http_status(500);
	echo 'Caught Exception: ',  $e->getMessage();
	exit;
}

if($_SERVER['HTTP_X_GITHUB_EVENT'] == 'push') {
	$files = array(
		'update' => array(),
		'delete' => array()
	);

	if(count($input['commits']) > 1) {
		$input['commits'] = array_reverse($input['commits']);
		foreach($input as $commit) {
			$files['update'] = array_merge($files['update'], $commit['added'], $commit['modified']);
			$files['delete'] = array_merge($files['delete'], $commit['removed']);
		}

		$json = file_get_contents($input['compare']);
		$json = json_decode($json, true);

		foreach($json['files'] as $file) {
			$files[$file['status']=='removed' ? 'delete' : 'update'][] = $file['filename'];
		}

		unset($json);
	} else {
		$files['update'] = array_merge($input['commits'][0]['added'], $input['commits'][0]['modified']);
		$files['delete'] = $input['commits'][0]['removed'];
	}

	$files['update'] = array_unique($files['update']);
	$files['delete'] = array_unique($files['delete']);
	//$files['update'] = array_diff($files['update'], $files['delete']);

	foreach($files['delete'] as $file) {
		echo "Delete: $file  [";
		echo (unlink('../'.$file) ? 'Success' : 'Failed!');
		echo "]\n";
	}

	foreach($files['update'] as $file) {
		if(in_array($file, array('.gitattributes', '.gitignore', 'config.inc.php', 'README.md'))) continue;
		echo "Update: $file  ";
		try {
			$data = file_get_contents('https://raw.githubusercontent.com/WHUT-SIA/MHS/master/'.$file);
			$dir = dirname('../'.$file);
			if(!is_dir($dir)) mkdirs($dir);
			if(file_put_contents('../'.$file, $data) === false) throw new Exception("Save file $file failed!");
		} catch (Exception $e) {
			echo "[Failed!]\n";
			continue;
		}
		echo "[Success]\n";
	}
}

echo "\n\n======================================== HTTP Body ========================================\n";
echo "User-Agent: {$_SERVER['HTTP_USER_AGENT']}\n";
echo "X-GitHub-Delivery: {$_SERVER['HTTP_X_GITHUB_DELIVERY']}\n";
echo "X-GitHub-Event: {$_SERVER['HTTP_X_GITHUB_EVENT']}\n";
echo "X-Hub-Signature: {$_SERVER['HTTP_X_HUB_SIGNATURE']}\n";

echo "\n\n========================================= Payload =========================================\n";
print_r($input);
