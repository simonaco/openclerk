<?php

/**
 * LTCMine.ru balance job.
 */

$exchange = "ltcmineru";

// get the relevant address
$q = db()->prepare("SELECT * FROM accounts_ltcmineru WHERE user_id=? AND id=?");
$q->execute(array($job['user_id'], $job['arg_id']));
$account = $q->fetch();
if (!$account) {
	throw new JobException("Cannot find a $exchange account " . $job['arg_id'] . " for user " . $job['user_id']);
}

$data = json_decode(crypto_get_contents(crypto_wrap_url("http://ltcmine.ru/apiex?act=getuserstatsext&key=" . urlencode($account['api_key']))), true);
if ($data === null) {
	throw new ExternalAPIException("Invalid JSON detected (null).");
} else {
	if (!isset($data['account_stat']['user_balance'])) {
		throw new ExternalAPIException("No user balance found");
	}

	$balances = array('ltc' => $data['account_stat']['user_balance']);
	foreach ($balances as $currency => $balance) {

		if (!is_numeric($balance)) {
			throw new ExternalAPIException("$exchange $currency balance is not numeric");
		}
		crypto_log("$exchange $currency balance for user " . $job['user_id'] . ": " . $balance);

		// disable old instances
		$q = db()->prepare("UPDATE balances SET is_recent=0 WHERE is_recent=1 AND user_id=:user_id AND exchange=:exchange AND account_id=:account_id AND currency=:currency");
		$q->execute(array(
			"user_id" => $job['user_id'],
			"account_id" => $account['id'],
			"exchange" => $exchange,
			"currency" => $currency,
		));

		// we have a balance; update the database
		$q = db()->prepare("INSERT INTO balances SET user_id=:user_id, exchange=:exchange, account_id=:account_id, balance=:balance, currency=:currency, is_recent=1");
		$q->execute(array(
			"user_id" => $job['user_id'],
			"account_id" => $account['id'],
			"exchange" => $exchange,
			"currency" => $currency,
			"balance" => $balance,
			// we ignore server_time
		));
		crypto_log("Inserted new $exchange $currency balances id=" . db()->lastInsertId());

	}
}
