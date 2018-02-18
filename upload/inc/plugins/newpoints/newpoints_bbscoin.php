<?php
/***************************************************************************
 *
 *   BBSCoin Plugin
 *	 Author: BBSCoin Foundation
 *   
 *   Website: https://bbscoin.xyz
 *
 *   Dependency: NewPoints Plugin
 *
 ***************************************************************************/
 
/****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("global_intermediate", "newpoints_bbscoin_nav", 10);
$plugins->add_hook("newpoints_start", "newpoints_bbscoin", 10);

function newpoints_bbscoin_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "BBSCoin Exchange",
		"description"	=> "You can use this plugin exchange between BBSCoin and Points.",
		"website"		=> "https://bbscoin.xyz",
		"author"		=> "BBSCoin Foundation",
		"authorsite"	=> "https://bbscoin.xyz",
		"version"		=> "1.0.2",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}


function newpoints_bbscoin_uninstall()
{
	global $db;
	$collation = $db->build_create_table_collation();

	// drop tables
	if($db->table_exists("newpoints_bbscoin_orders"))
    {
        $db->drop_table('newpoints_bbscoin_orders');
	}
	if($db->table_exists("newpoints_bbscoin_locks"))
    {
        $db->drop_table('newpoints_bbscoin_locks');
	}
	newpoints_remove_templates("'newpoints_bbscoin_links','newpoints_bbscoin_main'");

	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	find_replace_templatesets("header_welcomeblock_member", '#'.preg_quote('{$newpoints_bbscoin_links}').'#', '', 0);
}

function newpoints_bbscoin_activate()
{
	global $db, $mybb;
	// add settings
	// take a look at inc/plugins/newpoints.php to know exactly what each parameter means
	newpoints_add_setting('newpoints_bbscoin_pay_ratio', 'newpoints_bbscoin', 'BBSCoin -> Point', 'BBSCoin to Point Exchange Rate', 'text', "0.1", 1);
	newpoints_add_setting('newpoints_bbscoin_pay_to_coin_ratio', 'newpoints_bbscoin', 'BBSCoin <- Point', 'Point To BBSCoin Exchange Rate', 'text', "10", 2);
	newpoints_add_setting('newpoints_bbscoin_pay_to_bbscoin', 'newpoints_bbscoin', 'Withdraw BBSCoin', 'Allow get BBSCoin by points', 'yesno', 1, 3);
	newpoints_add_setting('newpoints_bbscoin_wallet_address', 'newpoints_bbscoin', 'Site BBSCoin Wallet', 'Your wallet address to receive BBSCoin', 'text', '', 4);
	newpoints_add_setting('newpoints_bbscoin_walletd', 'newpoints_bbscoin', 'Walletd Service URL', 'Your walletd service url', 'text', 'http://127.0.0.1:8070/json_rpc', 5);
	newpoints_add_setting('newpoints_bbscoin_confirmed_blocks', 'newpoints_bbscoin', 'Transfer Required Confirmed Blocks', 'The confirmation number of transaction', 'text', '3', 6);
	rebuild_settings();
	global $db;
	$collation = $db->build_create_table_collation();
	
	// create tables
	if(!$db->table_exists("newpoints_bbscoin_orders"))
    {
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."newpoints_bbscoin_orders` (
              `orderid` char(50) NOT NULL,
              `transaction_hash` char(64) NOT NULL,
              `address` char(100) NOT NULL,
              `dateline` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`orderid`),
              UNIQUE `transaction_hash` (`transaction_hash`),
              KEY `address` (`address`, `dateline`)
            ) ENGINE=MyISAM{$collation}");
	}

	if(!$db->table_exists("newpoints_bbscoin_locks"))
    {
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."newpoints_bbscoin_locks` (
              `uid` int(10) unsigned NOT NULL DEFAULT '0',
              `dateline` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`uid`)
            ) ENGINE=MyISAM{$collation}");
	}

	newpoints_add_template('newpoints_bbscoin_links', '<li><a href="{$mybb->settings[\'bburl\']}/newpoints.php?action=bbscoin">{$lang->newpoints_bbscoin_usercp_nav_name}</a></li>');
	newpoints_add_template('newpoints_bbscoin_main', '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->newpoints_bank}</title>
{$headerinclude}
{$javascript}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top">

<form action="newpoints.php" method="POST">
<input type="hidden" name="postcode" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="bbscoin_to_points" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->newpoints_bbscoin_topoint}</strong></td>
</tr>
<tr>
<td class="trow1" width="100%" colspan="2"><strong>{$lang->newpoints_bbscoin_topoint_desc}{$mybb->settings[\'newpoints_bbscoin_pay_ratio\']}</td>
</tr>
<tr>
<td class="trow2" width="50%"><strong>{$lang->newpoints_bbscoin_topoint_deposit}:</strong></td>
<td class="trow2" width="50%"><input type="text" name="amount" id="addfundamount" onkeyup="addcalcredit()" value="" class="textbox" size="20" /> {$lang->newpoints_bbscoin_points} {$lang->newpoints_bbscoin_topoint_cacl} <span id="desamount">0</span> BBS</td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_bbscoin_topoint_transactionhash}:</strong><br /><span class="smalltext">{$lang->newpoints_bbscoin_topoint_transactiontips}<br />{$mybb->settings[\'newpoints_bbscoin_wallet_address\']}</span></td>
<td class="trow1" width="50%"><input type="text" name="transaction_hash" value="" class="textbox" size="20" /></td>
</tr>
<tr>
<td class="tfoot" width="100%" colspan="2" align="center"><input type="submit" name="submit" value="{$lang->newpoints_submit}" class="button" /></td>
</tr>
</table>
</form>
<br />
<form action="newpoints.php" method="POST">
<input type="hidden" name="postcode" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="points_to_bbscoin" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="display: {$bbscoin_withdraw};">
<tr>
<td class="thead" colspan="2"><strong>{$lang->newpoints_bbscoin_tobbs}</strong></td>
</tr>
<tr>
<td class="trow1" width="100%" colspan="2"><strong>{$lang->newpoints_bbscoin_tobbs_desc}{$mybb->settings[\'newpoints_bbscoin_pay_to_coin_ratio\']}</td>
</tr>
<tr>
<td class="trow2" width="50%"><strong>{$lang->newpoints_bbscoin_tobbs_withdraw}:</strong><br /><span class="smalltext">{$lang->newpoints_bbscoin_points_balance} {$mybb->user[\'newpoints\']}</span> {$lang->newpoints_bbscoin_points}</td>
<td class="trow2" width="50%"><input type="text" name="amount" id="addcoinamount" onkeyup="addcalcoin()" value="" class="textbox" size="20" /> BBS {$lang->newpoints_bbscoin_tobbs_cacl}  <span id="coin_desamount">0</span> {$lang->newpoints_bbscoin_points}</td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_bbscoin_tobbs_address}:</strong><br /><span class="smalltext">{$lang->newpoints_bbscoin_tobbs_address_desc}</span></td>
<td class="trow1" width="50%"><input type="text" name="walletaddress" value="" class="textbox" size="20" /></td>
</tr>
<tr>
<td class="tfoot" width="100%" colspan="2" align="center"><input type="submit" name="submit" value="{$lang->newpoints_submit}" class="button" /></td>
</tr>
</table>
</form>
<script type="text/javascript">
function addcalcredit() {
var addfundamount = $(\'#addfundamount\').val().replace(/^0/, \'\');
var addfundamount = parseInt(addfundamount);
$(\'#desamount\').text(!isNaN(addfundamount) ? Math.ceil(((addfundamount / {$mybb->settings[\'newpoints_bbscoin_pay_ratio\']}) * 100)) / 100 : 0);
}

function addcalcoin() {
var addcoinamount = $(\'#addcoinamount\').val().replace(/^0/, \'\');
var addcoinamount = parseInt(addcoinamount);
$(\'#coin_desamount\').text(!isNaN(addcoinamount) ? Math.ceil(((addcoinamount / {$mybb->settings[\'newpoints_bbscoin_pay_to_coin_ratio\']}) * 100)) / 100 : 0);
}

</script>


</td>
</tr>
</table>
{$footer}
</body>
</html>');
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	find_replace_templatesets("header_welcomeblock_member", '#'.preg_quote('{$searchlink}').'#', '{$newpoints_bbscoin_links}'.'{$searchlink}');

}

function newpoints_bbscoin_deactivate()
{
	global $db, $mybb;
	// delete settings
	newpoints_remove_settings("'newpoints_bbscoin_pay_ratio','newpoints_bbscoin_pay_to_coin_ratio','newpoints_bbscoin_pay_to_bbscoin','newpoints_bbscoin_wallet_address','newpoints_bbscoin_walletd','newpoints_bbscoin_confirmed_blocks'");
	rebuild_settings();
}

function newpoints_bbscoin_nav()
{
    global $templates, $mybb, $newpoints_bbscoin_links, $lang;
	newpoints_lang_load('newpoints_bbscoin');
    eval("\$newpoints_bbscoin_links = \"".$templates->get('newpoints_bbscoin_links')."\";"); 
}


function newpoints_bbscoin($page)
{
	global $mybb, $db, $lang, $cache, $bbscoin_withdraw, $theme, $header, $templates, $plugins, $headerinclude, $footer, $options;

	if (!$mybb->user['uid']) {
		return;	
	}

	if ($mybb->input['action'] == "bbscoin")
	{
    	if (!$mybb->settings['newpoints_bbscoin_wallet_address']) {
            error($lang->newpoints_bbscoin_no_address);
        }

        if (!$mybb->settings['newpoints_bbscoin_pay_to_bbscoin']) {
            $bbscoin_withdraw = 'none';
        }

        $plugins->run_hooks("newpoints_bbscoin_page_start");


        eval("\$page = \"".$templates->get('newpoints_bbscoin_main')."\";");

        $plugins->run_hooks("newpoints_bbscoin_page_end");

    	output_page($page);
    } elseif ($mybb->input['action'] == "bbscoin_to_points") 
    {
        verify_post_check($mybb->input['postcode']);

        $plugins->run_hooks("newpoints_bbscoin_bbscoin_to_points_start");

    	// load language files
    	newpoints_lang_load('newpoints_bbscoin');

        $amount = $mybb->input['amount'];

        if($amount < 1) {
        	error($lang->newpoints_bbscoin_least);
        }

		$query = $db->simple_select('newpoints_bbscoin_locks', '*', " uid = '" . $mybb->user['uid'] . "'", array('limit' => 1));
    	if ($db->num_rows($query) > 0) {
            $lockinfo = $db->fetch_array($query);
            if (time() - $lockinfo['dateline'] > 10) {
        	    $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            }
            error($lang->newpoints_bbscoin_cc);
    	} else {
            $lockdata = array(
                'uid' => $mybb->user['uid'],
                'dateline' => time()
            );
            $db->insert_query("newpoints_bbscoin_locks", $lockdata, "uid");
        }

        $orderid = date('YmdHis').rand(100,999);
        $transaction_hash = trim($mybb->input['transaction_hash']);

		$query = $db->simple_select('newpoints_bbscoin_orders', '*', " transaction_hash = '" . $db->escape_string($transaction_hash) . "'", array('limit' => 1));
    	if ($db->num_rows($query) > 0) {
            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
        	error($lang->newpoints_bbscoin_used);
    	}

        $need_bbscoin = ceil((($amount / $mybb->settings['newpoints_bbscoin_pay_ratio']) * 100)) / 100;

        $orderinfo = array(
        	'uid' => $mybb->user['uid'],
        	'amount' => $amount,
        	'price' => $need_bbscoin,
        );

        $rsp_data = BBSCoinApi::getTransaction($mybb->settings['newpoints_bbscoin_walletd'], $transaction_hash); 
        $status_rsp_data = BBSCoinApi::getStatus($mybb->settings['newpoints_bbscoin_walletd']); 

        $blockCount = $status_rsp_data['result']['blockCount'];
        $transactionBlockIndex = $rsp_data['result']['transaction']['blockIndex'];
        $confirmed = $blockCount - $transactionBlockIndex + 1;
        if ($blockCount <= 0 || $transactionBlockIndex <= 0 || $confirmed <= $mybb->settings['newpoints_bbscoin_confirmed_blocks']) {
            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
        	error($lang->sprintf($lang->newpoints_bbscoin_notconfirmed, $mybb->settings['newpoints_bbscoin_confirmed_blocks']));
        }

        $trans_amount = 0;
        if ($rsp_data['result']['transaction']['transfers']) {
            foreach ($rsp_data['result']['transaction']['transfers'] as $transfer_item) {
                if ($transfer_item['address'] == $mybb->settings['newpoints_bbscoin_wallet_address']) {
                    $trans_amount += $transfer_item['amount'];
                }
            }
        }

        $trans_amount = $trans_amount / 100000000;
        if ($trans_amount == $need_bbscoin) {
            $db->insert_query("newpoints_bbscoin_orders", array(
                    'orderid' => $orderid,
                    'transaction_hash' => $transaction_hash,
                    'address' => '',
                    'dateline' => time(),
            ), "transaction_hash");
        	newpoints_addpoints($mybb->user['uid'], $orderinfo['amount']);

            newpoints_log('Deposit From BBSCoin', 'Points:'.$orderinfo['amount'].', BBSCoin: '.$need_bbscoin.', transaction_hash:'.$transaction_hash);

            $plugins->run_hooks("newpoints_bbscoin_bbscoin_to_points_succ");

            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            redirect($mybb->settings['bburl'] . "/member.php?action=profile&uid=".$mybb->user['uid'], $lang->newpoints_bbscoin_succ);
        } else {
            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            error($lang->newpoints_bbscoin_amount_error);
        }
    } elseif ($mybb->input['action'] == "points_to_bbscoin") 
    {
        verify_post_check($mybb->input['postcode']);

        $plugins->run_hooks("newpoints_bbscoin_points_to_bbscoins_start");

    	// load language files
    	newpoints_lang_load('newpoints_bbscoin');

        if(!$mybb->settings['newpoints_bbscoin_pay_to_bbscoin']) {
        	error($lang->newpoints_bbscoin_close_withdraw);
        }

        $amount = $mybb->input['amount'];
        $need_point = ceil((($amount / $mybb->settings['newpoints_bbscoin_pay_to_coin_ratio']) * 100)) / 100;

        if ($need_point < 1) {
        	error($lang->newpoints_bbscoin_least);
        }

        $walletaddress = trim($mybb->input['walletaddress']);

        if ($mybb->settings['newpoints_bbscoin_wallet_address'] == $walletaddress) {
            error($lang->newpoints_bbscoin_withdraw_error);
        }

        $real_price = $amount * 100000000 - 50000000;

        if ($real_price <= 0) {
            error($lang->newpoints_bbscoin_withdraw_too_low);
        }

    	$query = $db->simple_select('newpoints_bbscoin_locks', '*', " uid = '" . $mybb->user['uid'] . "'", array('limit' => 1));
    	if ($db->num_rows($query) > 0) {
            $lockinfo = $db->fetch_array($query);
            if (time() - $lockinfo['dateline'] > 10) {
        	    $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            }
            error($lang->newpoints_bbscoin_cc);
    	} else {
            $lockdata = array(
                'uid' => $mybb->user['uid'],
                'dateline' => time()
            );
            $db->insert_query("newpoints_bbscoin_locks", $lockdata, "uid");
        }

        if ($need_point > $mybb->user['newpoints']) {
            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
        	error($lang->newpoints_bbscoin_no_enough);
        }

        $orderid = date('YmdHis').rand(100,999);

        $rsp_data = BBSCoinApi::sendTransaction($mybb->settings['newpoints_bbscoin_walletd'], $mybb->settings['newpoints_bbscoin_wallet_address'], $real_price, $walletaddress);

        $trans_amount = 0;
        if ($rsp_data['result']['transactionHash']) {
            $db->insert_query("newpoints_bbscoin_orders", array(
                    'orderid' => $orderid,
                    'transaction_hash' => $rsp_data['result']['transactionHash'],
                    'address' => $walletaddress,
                    'dateline' => time(),
            ), "transaction_hash");
        	newpoints_addpoints($mybb->user['uid'], -$need_point);

            newpoints_log('Withdraw To BBSCoin', 'Points:'.$need_point.', BBSCoin:'.$amount.', address:'.$walletaddress);

            $plugins->run_hooks("newpoints_bbscoin_points_to_bbscoins_succ");

            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            redirect($mybb->settings['bburl'] . "/member.php?action=profile&uid=".$mybb->user['uid'], $lang->sprintf($lang->newpoints_bbscoin_withdraw_succ, $rsp_data['result']['transactionHash']));
        } else {
            $db->delete_query('newpoints_bbscoin_locks', "uid = '" . $mybb->user['uid'] . "'");
            error($lang->newpoints_bbscoin_fail);
        }

    }
}

class BBSCoinApi {

    public static function getUrlContent($url, $data_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BBSCoin');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $data;
    }

    public static function sendTransaction($walletd, $address, $real_price, $sendto) {
        $req_data = array(
          'params' => array(
              'anonymity' => 0,
              'fee' => 50000000,
              'unlockTime' => 0,
              'changeAddress' => $address,
              "transfers" => array(
               0 => array(
                    'amount' => $real_price,
                    'address' => $sendto,
                )
              )
          ),
          "jsonrpc" => "2.0",
          "method" => "sendTransaction"
        );

        $result = self::getUrlContent($walletd, json_encode($req_data)); 
        $rsp_data = json_decode($result, true);
        
        return $rsp_data;
    }

    public static function getStatus($walletd) {
        $status_req_data = array(
          "jsonrpc" => "2.0",
          "method" => "getStatus"
        );

        $result = self::getUrlContent($walletd, json_encode($status_req_data)); 
        $status_rsp_data = json_decode($result, true);
        return $status_rsp_data;
    }

    public static function getTransaction($walletd, $transaction_hash) {
        $req_data = array(
          "params" => array(
          	"transactionHash" => $transaction_hash
          ),
          "jsonrpc" => "2.0",
          "method" => "getTransaction"
        );

        $result = self::getUrlContent($walletd, json_encode($req_data)); 
        $rsp_data = json_decode($result, true);

        return $rsp_data;
    }

}

