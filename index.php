<?php
$userFound = false;

$stats = new StdClass();
$stats->totalSizediff = 0;
$stats->reviewCount = 0;
$stats->reviewCounts = array();

$manualReviewTypes = array(
	'Checked (again)' => 'approve',
	'Quality (again)' => 'approve-2',
	'Checked (first time)' => 'approve-i',
	'Quality (first time)' => 'approve-2-i',
	'Unapproved (was checked)' => 'unapprove',
	'Unapproved (was quality)' => 'unapprove-2'
);

$wikis = array(
	'Brood War' => 'starcraft',
	'StarCraft II' => 'starcraft2',
	'Dota 2' => 'dota2',
	'Hearthstone' => 'hearthstone',
	'Heroes' => 'heroes',
	'Smash Bros' => 'smash',
	'Counter-Strike' => 'counterstrike',
	'Overwatch' => 'overwatch',
	'Warcraft' => 'warcraft',
	'Fighting Games' => 'fighters',
	'Rocket League' => 'rocketleague'
);

function getReviewsSingle($curl, $request, $reviewType, $wiki, $url, &$stats) {

	curl_setopt($curl, CURLOPT_URL, $url);
	$json = curl_exec($curl);
	$jsonData = json_decode($json);

	if (isset($jsonData->query)) {
		$reviews = $jsonData->query->logevents;
		$stats->reviewCounts[$wiki][$reviewType] += count($reviews);
	}

	return isset($jsonData->continue) ? $jsonData->continue : 0;
}

function getReviews($curl, $request, &$stats) {
	global $manualReviewTypes;
	global $wikis;

	foreach ($wikis as $wiki) {
		$stats->reviewCounts[$wiki]= array('total' => 0);

		foreach ($manualReviewTypes as $reviewType) {
			$stats->reviewCounts[$wiki][$reviewType] = 0;

			$url = 'http://wiki.teamliquid.net/' . $wiki . '/api.php?action=query&list=logevents&leuser='
				. htmlspecialchars($request->user) . '&leprop=ids&leaction=review%2F' . $reviewType . '&lelimit=5000&continue=&format=json';

			$continue = getReviewsSingle($curl, $request, $reviewType, $wiki, $url, $stats);

			while( $continue != null ) {
				$explodeContinue = explode('|', $continue->lecontinue);
				$url = 'http://wiki.teamliquid.net/' . $wiki . '/api.php'
				       . '?action=query&list=logevents&leuser='
				       . htmlspecialchars($request->user)
				       . '&lestart=' . $explodeContinue[0]
				       . '&leprop=ids&leaction=review%2F' . $reviewType
				       . '&lelimit=5000'
				       . '&continue=' . $continue->continue
				       . '&format=json';
				$continue = getReviewsSingle($curl, $request, $reviewType, $wiki, $url, $stats);
				usleep(500000);
			}

			$stats->reviewCounts[$wiki]['total'] += $stats->reviewCounts[$wiki][$reviewType];
		}
		$stats->reviewCount += $stats->reviewCounts[$wiki]['total'];
	}

}

$request = new StdClass();
$request->user = isset($_GET['user']) ? ucfirst($_GET['user']) : 'ChapatiyaqPTSM';
//$request->wiki = isset($_GET['wiki']) ? $_GET['wiki'] : 'starcraft2';

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; userreviewstats/1.0; chapatiyaq@gmail.com)',
	CURLOPT_RETURNTRANSFER => 1,
	CURLOPT_ENCODING => '',
	CURLOPT_TIMEOUT => 60
));

getReviews($curl, $request, $stats);

curl_close($curl);

?>
<!DOCTYPE html>
<html>
<head>
	<title>Review stats - User:<?php echo $request->user; ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>
	<h2>User:<?php echo $request->user; ?></h2>
<?php if ($stats->reviewCount != 0) { ?>
	<h3><?php echo $stats->reviewCount; ?> total manual reviews</h3>
	<ul>
	<?php foreach ($wikis as $wikiName => $wiki) { ?>
		<li><a href="http://wiki.teamliquid.net/<?php echo $wiki; ?>"><?php echo $wikiName; ?></a></li>
		<ul>
		<?php if ($stats->reviewCounts[$wiki]['total'] === 0) { ?>
			<li>No manual reviews found for this user on this wiki.</li>
		<?php } else { ?>
			<li><?php echo $stats->reviewCounts[$wiki]['total']; ?> manual reviews.</li>
			<ul>
				<?php foreach ($manualReviewTypes as $reviewTypeText => $reviewType) { ?>
				<li><?php echo $reviewTypeText;?>: <?php echo $stats->reviewCounts[$wiki][$reviewType];?></li>
				<?php } ?>
			</ul>
		<?php } ?>
		</ul>
	<?php } ?>
<?php } else { ?>
	No manual reviews for this user.
<?php } ?>
</body>
</html>