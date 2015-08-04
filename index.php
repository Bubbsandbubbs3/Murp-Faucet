<?php
/* 
 * See: https://faucetbox.com/faucetinabox/
 *
 * Copyright 2014 LiveHome Sp. z o. o.
 *
 * All rights reserved. Redistribution and modification of this file in any form is forbidden.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
 *
 */


$version = '49';


if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}
 
if(stripos($_SERVER['REQUEST_URI'], '@') !== FALSE ||
   stripos(urldecode($_SERVER['REQUEST_URI']), '@') !== FALSE) {
    header("Location: ."); die('Please wait...');
}

session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', false);
error_reporting(-1);

$session_prefix = crc32(__FILE__);

$disable_curl = false;
$verify_peer = true;
$local_cafile = false;
require_once("config.php");
if(!isset($connection_options)) {
    $connection_options = array(
        'disable_curl' => $disable_curl,
        'local_cafile' => $local_cafile,
        'verify_peer' => $verify_peer,
        'force_ipv4' => false
    );
}
if(!isset($connection_options['verify_peer'])) {
    $connection_options['verify_peer'] = $verify_peer;
}

if(isset($display_errors)) {
    ini_set('display_errors', $display_errors);
}

require_once('libs/faucetbox.php');

try {
    $sql = new PDO($dbdsn, $dbuser, $dbpass, array(PDO::ATTR_PERSISTENT => true,
                                                   PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch(PDOException $e) {
    die("Can't connect to database. Check your config.php.");
}


$db_updates = array(
    15 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('version', '15');"),
    17 => array("ALTER TABLE `Faucetinabox_Settings` CHANGE `value` `value` TEXT NOT NULL;", "INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('balance', 'N/A');"),
    33 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('ayah_publisher_key', ''), ('ayah_scoring_key', '');"),
    34 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('custom_admin_link_default', 'true')"),
    38 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('reverse_proxy', 'none')", "INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('default_captcha', 'recaptcha')"),
    41 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('captchme_public_key', ''), ('captchme_private_key', ''), ('captchme_authentication_key', ''), ('reklamper_enabled', '')"),
    46 => array("INSERT IGNORE INTO `Faucetinabox_Settings` (`name`, `value`) VALUES ('last_balance_check', '0')")
);

$default_data_query = <<<QUERY
create table if not exists Faucetinabox_Settings (
    `name` varchar(64) not null,
    `value` text not null,
    primary key(`name`)
);
create table if not exists Faucetinabox_IPs (
    `ip` varchar(20) not null,
    `last_used` timestamp not null,
    primary key(`ip`)
);
create table if not exists Faucetinabox_Addresses (
    `address` varchar(60) not null,
    `ref_id` int null,
    `last_used` timestamp not null,
    primary key(`address`)
);
create table if not exists Faucetinabox_Refs (
    `id` int auto_increment not null,
    `address` varchar(60) not null unique,
    `balance` bigint unsigned default 0,
    primary key(`id`)
);
create table if not exists Faucetinabox_Pages (
    `id` int auto_increment not null,
    `url_name` varchar(50) not null unique,
    `name` varchar(255) not null,
    `html` text not null,
    primary key(`id`)
);

INSERT IGNORE INTO Faucetinabox_Settings (name, value) VALUES
('apikey', ''),
('timer', '180'),
('rewards', '10*100, 1*500'),
('referral', '15'),
('solvemedia_challenge_key', ''),
('solvemedia_verification_key', ''),
('solvemedia_auth_key', ''),
('recaptcha_private_key', ''),
('recaptcha_public_key', ''),
('ayah_publisher_key', ''),
('ayah_scoring_key', ''),
('captchme_public_key', ''),
('captchme_private_key', ''),
('captchme_authentication_key', ''),
('reklamper_enabled', ''),
('name', 'Faucet in a Box'),
('short', 'Just another Faucet in a Box :)'),
('template', 'default'),
('custom_body_cl_default', ''),
('custom_box_bottom_cl_default', ''),
('custom_box_bottom_default', ''),
('custom_box_top_cl_default', ''),
('custom_box_top_default', ''),
('custom_box_left_cl_default', ''),
('custom_box_left_default', ''),
('custom_box_right_cl_default', ''),
('custom_box_right_default', ''),
('custom_css_default', '/* custom_css */\\n/* center everything! */\\n.row {\\n    text-align: center;\\n}\\n#recaptcha_widget_div, #recaptcha_area {\\n    margin: 0 auto;\\n}\\n/* do not center lists */\\nul, ol {\\n    text-align: left;\\n}'),
('custom_footer_cl_default', ''),
('custom_footer_default', ''),
('custom_main_box_cl_default', ''),
('custom_palette_default', ''),
('custom_admin_link_default', 'true'),
('version', '$version'),
('currency', 'BTC'),
('balance', 'N/A'),
('reverse_proxy', 'none'),
('last_balance_check', '0'),
('default_captcha', 'recaptcha')
;
QUERY;

// ****************** START ADMIN TEMPLATES
$master_template = <<<TEMPLATE
<!DOCTYPE html>
<html>
    <head>
        <title>Faucet in a Box</title>
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/css/bootstrap.min.css">
        <link rel="stylesheet" id="palette-css" href="data:text/css;base64,IA==">
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.6.2/css/bootstrap-select.min.css">
        <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.6.2/js/bootstrap-select.min.js"></script>
        <style type="text/css">
        a, .btn, tr, td, .glyphicon{
            transition: all 0.2s ease-in;
            -o-transition: all 0.2s ease-in;
            -webkit-transition: all 0.2s ease-in;
            -moz-transition: all 0.2s ease-in;
        }
        .form-group {
            margin: 15px !important;
        }
        textarea.form-control {
            min-height: 120px;
        }
        .tab-content > .active {
            border-radius: 0px 0px 4px 6px;
            margin-top: -1px;
        }
        .prev-box {
            border-radius: 4px;
        }
        .prev-box > .btn {
            min-width: 45px;
            height: 33px;
            font-weight: bold;
        }
        .prev-box > .text-white {
            text-shadow: 0 0 2px black;
        }
        .prev-box > .active {
            margin-top: -2px;
            height: 36px;
            font-weight: bold;
            font-size: 130%;
            border-radius: 3px !important;
            box-shadow: 0px 1px 2px #333;
        }
        .prev-box > .transparent {
            border: 1px dotted #FF0000;
            box-shadow:  inset 0px 0px 5px #FFF;
        }
        .prev-box > .transparent.active {
            box-shadow: 0px 1px 2px #333, inset 0px 0px 5px #FFF;
        }
        .picker-label {
            padding-top: 11px;
        }
        .bg-black{
            background: #000;
        }
        .bg-white{
            background: #fff;
        }
        .text-black{
            color: #000;
        }
        .text-white{
            color: #fff;
        }
        </style>
    </head>
    <body>
        <div class="container">
        <h1>Welcome to your Faucet in a Box Admin Page!</h1><hr>
        <:: content ::>
        </div>
    </body>
</html>
TEMPLATE;

$admin_template = <<<TEMPLATE
<noscript><div class="alert alert-danger" role="alert">You have disabled javascript. Javascript is required for the panel to work good.</div></noscript>
<:: version_check ::>
<:: connection_error ::>
<:: curl_warning ::>
<:: send_coins_message ::>
<form method="POST" class="form-horizontal" role="form">

    <div role="tabpanel">
       
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#basic" aria-controls="basic" role="tab" data-toggle="tab">Basic</a></li>
            <li role="presentation"><a href="#captcha" aria-controls="captcha" role="tab" data-toggle="tab">Captcha</a></li>
            <li role="presentation"><a href="#templates" aria-controls="templates" role="tab" data-toggle="tab">Templates</a></li>
            <li role="presentation"><a href="#pages" aria-controls="pages" role="tab" data-toggle="tab">Pages</a></li>
            <li role="presentation"><a href="#advanced" aria-controls="advanced" role="tab" data-toggle="tab">Advanced</a></li>
            <li role="presentation"><a href="#send-coins" aria-controls="send-coins" role="tab" data-toggle="tab">Manually send coins</a></li>
            <li role="presentation"><a href="#reset" aria-controls="reset" role="tab" data-toggle="tab">Reset to defaults</a></li>
        </ul>
        
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="basic">
                <h2>Basic</h2>
                <h3>FaucetBOX.com API</h3>
                <div class="form-group">
                    <:: invalid_key ::>
                    <label for="apikey" class=" control-label">FaucetBOX.com API key</label>
                    <p>You can get it from <a href="https://faucetbox.com/">FaucetBOX.com dashboard</a> (you have to register and log in)</p>
                    <input type="text" class="form-control" name="apikey" value="<:: apikey ::>">
                </div>
                <div class="form-group">
                    <label for="currency" class=" control-label">Currency</label>
                    <p>Select currency you want to use.</p>
                    <select id="currency" class="form-control selectpicker" name="currency">
                        <:: currencies ::>
                    </select>
                </div>
                <h3>Rewards</h3>
                <div class="form-group">
                    <p>How much users can get from you? You can set multiple rewards (separate with comma) and set weights for them, to define how plausible each reward will be. <br>Examples: <code>100</code>, <code>50, 150, 300</code>, <code>10*50, 2*100</code>. The last example means 50 satoshi or DOGE 10 out of 12 times, 100 satoshi or DOGE 2 out of 12 times.</p>
                    <p>It should be in satoshi (which means 0.00000001 COIN) for everything except DOGE. For DOGE it's in whole coins.</p>
                    <input type="text" class="form-control" name="rewards" value="<:: rewards ::>">
                </div>
                <h3>Options</h3>
                <div class="form-group">
                    <label for="name" class=" control-label">Faucet name</label>
                    <input type="text" class="form-control" name="name" value="<:: name ::>">
                </div>
                <div class="form-group">
                    <label for="short" class=" control-label">Short description</label>
                    <input type="text" class="form-control" name="short" value="<:: short ::>">
                </div>
                <div class="form-group">
                    <label for="timer" class=" control-label">Timer (in minutes)</label>
                    <p>How often users can get coins from you?</p>
                    <input type="text" class="form-control" name="timer" value="<:: timer ::>">
                </div>
                <div class="form-group">
                    <label for="referral" class=" control-label">Referral earnings (in percents) (0 to disable):</label> <input type="text" class="form-control" name="referral" value="<:: referral ::>">
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="captcha">
                <h2>Captcha</h2>
                <div class="row">
                    <div class="form-group">
                        <p class="alert alert-info">Some captcha systems may be unsafe and fail to stop bots. reCaptcha is considered the safest, but you should always read opinions about your chosen Captcha system first.</p>
                        <label for="default_captcha" class="control-label">Default captcha:</label>
                        <select class="form-control selectpicker" name="default_captcha" id="default_captcha">
                            <option value="SolveMedia">SolveMedia</option>
                            <option value="reCaptcha">reCaptcha</option>
                            <option value="AreYouAHuman">Are You A Human</option>
                            <option value="CaptchMe">CaptchMe.net</option>
                            <option value="Reklamper">Reklamper.com</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <h4>reCaptcha</h4>
                        <div class="form-group" id="recaptcha">
                            <p>Get your keys <a href="https://www.google.com/recaptcha/admin#list">here</a>.</p>
                            <label for="recaptcha_public_key" class=" control-label">reCaptcha public key:</label>
                            <input type="text" class="form-control" name="recaptcha_public_key" value="<:: recaptcha_public_key ::>">
                            <label for="recaptcha_private_key" class=" control-label">reCaptcha private key:</label>
                            <input type="text" class="form-control" name="recaptcha_private_key" value="<:: recaptcha_private_key ::>">
                            <label><input type="checkbox" class="captcha-disable-checkbox"> Turn on this captcha system</label>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <h4>Are You A Human</h4>
                        <div class="form-group" id="ayah">
                            <p>Get your keys <a href="https://portal.areyouahuman.com/dashboard">here</a>.</p>
                            <label for="ayah_publisher_key" class=" control-label">Are You A Human publisher key:</label>
                            <input type="text" class="form-control" name="ayah_publisher_key" value="<:: ayah_publisher_key ::>">
                            <label for="ayah_scoring_key" class=" control-label">Are You A Human scoring key:</label>
                            <input type="text" class="form-control" name="ayah_scoring_key" value="<:: ayah_scoring_key ::>">
                            <label><input type="checkbox" class="captcha-disable-checkbox"> Turn on this captcha system</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <h4>SolveMedia</h4>
                        <div class="form-group" id="solvemedia">
                            <p>Get your keys <a href="https://portal.solvemedia.com/portal/">here</a> (select <em>Sites</em> from the menu after logging in).</p>
                            <label for="solvemedia_challenge_key" class=" control-label">SolveMedia challenge key:</label>
                            <input type="text" class="form-control" name="solvemedia_challenge_key" value="<:: solvemedia_challenge_key ::>">
                            <label for="solvemedia_verification_key" class=" control-label">SolveMedia verification key:</label>
                            <input type="text" class="form-control" name="solvemedia_verification_key" value="<:: solvemedia_verification_key ::>">
                            <label for="solvemedia_auth_key" class=" control-label">SolveMedia authentication key:</label>
                            <input type="text" class="form-control" name="solvemedia_auth_key" value="<:: solvemedia_auth_key ::>">
                            <label><input type="checkbox" class="captcha-disable-checkbox"> Turn on this captcha system</label>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <h4>CaptchMe.net</h4>
                        <div class="form-group" id="captchme">
                            <p>Get your keys <a href="http://portal.captchme.net/publisher/">here</a>.</p>
                            <label for="captchme_public_key" class=" control-label">CaptchMe.net public key:</label>
                            <input type="text" class="form-control" name="captchme_public_key" value="<:: captchme_public_key ::>">
                            <label for="captchme_private_key" class=" control-label">CaptchMe.net private key:</label>
                            <input type="text" class="form-control" name="captchme_private_key" value="<:: captchme_private_key ::>">
                            <label for="captchme_authentication_key" class=" control-label">CaptchMe.net authentication key:</label>
                            <input type="text" class="form-control" name="captchme_authentication_key" value="<:: captchme_authentication_key ::>">
                            <label><input type="checkbox" class="captcha-disable-checkbox"> Turn on this captcha system</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <h4>Reklamper.com</h4>
                        <div class="form-group" id="reklamper">
                            <p>You don't have to type any keys, just add your site <a href="http://reklamper.com/">here</a> and check checkbox below.</p>
                            <p>Please note, that Reklamper.com Captcha WON'T work if you use HTTPS on your faucet.</p>
                            <label><input type="checkbox" name="reklamper_enabled" <:: reklamper_enabled ::>> Turn on this captcha system</label>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <h4>And more</h4>
                        <p><span class="glyphicon glyphicon-plus"></span> We'll add more captcha systems soon.</p>
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="templates">
                <h2>Template options</h2>
                <div class="form-group">
                    <div class="col-xs-12 col-sm-2 col-lg-1">
                        <label for="template" class=" control-label">Template:</label>
                    </div>
                    <div class="col-xs-3">
                        <select id="template-select" name="template" class="selectpicker"><:: templates ::></select>
                    </div>
                </div>
                <div id="template-options">
                <:: template_options ::>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="pages">
                <h2>Pages</h2>
                <p>Here you can create, delete and edit custom static pages.</p>
                <ul class="nav nav-tabs pages-nav" role="tablist">
                    <li class="pull-right"><button type="button" id="pageAddButton" class="btn btn-info"><span class="glyphicon">+</span> Add new page</button></li>
                    <:: pages_nav ::>
                </ul>
                <div id="pages-inner" class="tab-content">
                    <:: pages ::>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="advanced">
                <h2>Advanced</h2>
                <h3>Reverse Proxy</h3>
                <div class="form-group">
                    <p class="alert alert-danger"><b>Be careful! This is an advanced feature. Don't use it unless you know what you're doing. If you set it wrong or you don't properly configure your proxy AND your server YOU MAY LOSE YOUR COINS!</b></p>
                    <p class="alert alert-info">This feature is experimental! It may not work properly and may lead you to losing coins. You have been warned.</p>
                    <p>This setting allows you to change the method of identifying users. By default Faucet in a Box will use the connecting IP address. Hovewer if you're using a reverse proxy, like CloudFlare or Incapsula, the connecting IP address will always be the address of the proxy. That results in all faucet users sharing the same timer. If you set this option to a correct proxy, then Faucet in a Box will use a corresponding HTTP Header instead of IP address.</p>
                    <p>However you MUST prevent anyone from bypassing the proxy. HTTP Headers can be spoofed, so if someone can access your page directly, then he can send his own headers, effectively ignoring the timer you've set and stealing all your coins!</p>
                    <p>Faucet in a Box has a security feature that will disable Reverse Proxy support if it detects any connection that has bypassed the proxy. Hovewer the detection is not perfect, so you shouldn't rely on it. Instead make proper precautions, for example by configuring your firewall to only allow connections from your proxy IP addresses.</p>
                    <p>If you're using a Reverse Proxy (CloudFlare or Incapsula) choose it from the list below. If your provider is not listed below contact us at support@faucetbox.com</p>
                    <p><em>None</em> is always a safe setting, but - as explained above - the timer may be shared between all your users if you're using a proxy.</p>
                    <:: reverse_proxy_changed_alert ::>
                    <label for="reverse_proxy" class="control-label">Reverse Proxy provider:</label>
                    <select id="reverse_proxy" name="reverse_proxy" class="form-control selectpicker">
                        <option value="cloudflare">CloudFlare (CF-Connecting-IP)</option>
                        <option value="incapsula">Incapsula (Incap-Client-IP)</option>
                        <option value="none">None (Connecting IP address)</option>
                    </select>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="send-coins">
                <h2>Manually send coins</h2>
                <div class="form-group">
                    <p class="alert alert-info">You can use the form below to send coins to given address manaully</p>
                    <label for="" class="control-label">Amount in satoshi:</label>
                    <input type="text" class="form-control" name="send_coins_amount" value="1" id="input_send_coins_amount">
                    <label for="" class="control-label">Currency:</label>
                    <input type="text" class="form-control" name="send_coins_currency" value="<:: currency ::>" disabled>
                    <label for="" class="control-label">Receiver address:</label>
                    <input type="text" class="form-control" name="send_coins_address" value=""id="input_send_coins_address">
                </div>
                <div class="form-group">
                    <div class="alert alert-info">
                        Are you sure you would like to send <span id="send_coins_satoshi">0</span> satoshi (<span id="send_coins_bitcoins">0.00000000</span> <:: currency ::>) to <span id="send_coins_address">address</span>? 
                        <input class="btn btn-primary pull-right" style="margin-top: -7px;" type="submit" name="send_coins" value="Yes, send coins">
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="reset">
                <h2>Reset to defaults</h2>
                <p class="text-center">
                    <input type="submit" name="reset" class="btn btn-warning btn-lg" style="" value="Reset settings to defaults">
                </p>
            </div>
        </div>
        
    </div>
    
    <hr>
    
    <div class="form-group">
        <input type="submit" name="save_settings" class="btn btn-primary btn-lg" value="Save">
        <a href="?p=logout" class="btn btn-default btn-lg pull-right">Logout</a>
    </div>
    <script type="text/javascript">

    function renumberPages(){
        $(".pages-nav > li").each(function(index){
            if(index != 0){
                $(this).children().first().attr("href", "#page-wrap-" + index);
                $(this).children().first().text("Page " + index);
            }
        });
        $("#pages-inner > div.tab-pane").each(function(index){
            var i = index+1;
            $(this).attr("id", "page-wrap-" + i);
            $(this).children().each(function(i2){
                var ending = "html";
                var item = "textarea";
                if(i2 == 0){
                    ending = "name";
                    item = "input";
                }

                $(this).children('label').attr("for", "pages." + i + "." + ending);
                $(this).children(item).attr("id", "pages." + i + "." + ending).attr("name", "pages[" + i + "][" + ending + "]");
            });
        });
    }

    function deletePage(btn) {
        $(btn).parent().remove();
        $(".pages-nav > .active").remove();
        $(".pages-nav > li:nth-child(2) > a").tab('show');
        renumberPages();
    }
    
    function reloadSendCoinsConfirmation() {
        
        var satoshi = $("#input_send_coins_amount").val();
        var bitcoin = satoshi / 100000000;
        var address = $("#input_send_coins_address").val();
        
        $("#send_coins_satoshi").text(satoshi);
        $("#send_coins_bitcoins").text(bitcoin.toFixed(8));
        $("#send_coins_address").text(address);
        
    }
    
    var tmp = [];
    
    $(function() {
        
        $("#input_send_coins_amount, #input_send_coins_address").change(reloadSendCoinsConfirmation).keydown(reloadSendCoinsConfirmation).keyup(reloadSendCoinsConfirmation).keypress(reloadSendCoinsConfirmation);
        
        $("#pageAddButton").click(function() {
            var i = $("#pages-inner").children("div").length.toString();
            var j = parseInt(i)+1;
            var newpage = "<:: page_form_template ::>"
                        .replace(/<:: i ::>/g, i)
                        .replace("<:: html ::>", '')
                        .replace("<:: page_name ::>", '');
            $("#pages-inner").append(newpage);
            var newtab = "<:: page_nav_template ::>"
                        .replace(/<:: i ::>/g, i);
            $('.pages-nav').append(newtab);
            renumberPages();
            $(".pages-nav > li").last().children().first().tab('show');
        });
        $(".pages-nav > li:nth-child(2)").addClass('active');
        $('#pages-inner').children().first().addClass('active');
        
        $('.pages-nav a').click(function (e) {
            e.preventDefault();
            $(this).tab('show');
        });
        $("#template-select").change(function() {
            var t = $(this).val();
            $.post("", { "get_options": t }, function(data) { $("#template-options").html(data); $('.selectpicker').selectpicker(); });
        });
        $("#reverse_proxy").val("<:: reverse_proxy ::>"); //must be before selectpicker render
        $("#default_captcha").val("<:: default_captcha ::>"); //must be before selectpicker render
        $('.selectpicker').selectpicker(); //render selectpicker on page load
        
        $('.nav-tabs a').click(function (e) {
            e.preventDefault()
            $(this).tab('show');
        });
		
        $(".captcha-disable-checkbox").each(function(){
            $(this).parent().parent().find("input[type=text]").each(function(){
                if ($(this).val() == '') {
                    $(this).parent().find(".captcha-disable-checkbox").attr("checked", false);
                    $(this).parent().find("input[type=text]").attr("readonly", true);
                } else {
                    $(this).parent().find(".captcha-disable-checkbox").attr("checked", true);
                    $(this).parent().find("input[type=text]").attr("readonly", false);
                }
            });
        }).change(function(){
            if ($(this).prop("checked")) {
                $(this).parent().parent().find("input[type=text]").each(function(){
                    $(this).val(tmp[$(this).attr("name")]);
                    $(this).attr("readonly", false);
                });
            } else {
                $(this).parent().parent().find("input[type=text]").each(function(){
                    tmp[$(this).attr("name")] = $(this).val();
                    $(this).val("");
                    $(this).attr("readonly", true);
                });
            }
        });
        
    });
    </script>
</form>
TEMPLATE;

$admin_login_template = <<<TEMPLATE
<form method="POST" class="form-horizontal" role="form">
    <div class="form-group">
        <label for="password" class=" control-label">Password:</label>
        <input type="password" class="form-control" name="password">
    </div>
    <div class="form-group">
        <input type="submit" class="btn btn-primary btn-lg" value="Login">
    </div>
</form>
<div class="alert alert-warning alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
Don't remember? <a href="?p=password-reset">Reset your password</a>.
</div>
TEMPLATE;

$pass_template = <<<TEMPLATE
<div class="alert alert-info" role="alert">
    Your password: <:: password ::>. Make sure to save it. <a class="alert-link" href="?p=admin">Click here to continue</a>.
</div>
TEMPLATE;

$pass_reset_template = <<<TEMPLATE
<form method="POST">
    <div class="form-group">
        <label for="dbpass" class="control-label">To reset your Admin Password, enter your database password here:</label>
        <input type="password" class="form-control" name="dbpass">
    </div>
    <p class="form-group alert alert-info" role="alert">
        You must enter the same password you've entered in your config.php file.
    </p>
    <input type="submit" class="form-group pull-right btn btn-warning" value="Reset password">
</form>
TEMPLATE;

$invalid_key_error_template = <<<TEMPLATE
<div class="alert alert-danger" role="alert">
    You've entered an invalid API key!
</div>
TEMPLATE;

$new_version_template = <<<TEMPLATE
<div class="alert alert-warning alert-dismissible" role="alert">
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
<span style="line-height: 34px">There's a new version of Faucet in a Box available!
Your version: $version; new version: <strong><:: version ::></strong></span>
<span style="float: right">
<a class="btn btn-primary" href="<:: url ::>">Download version <:: version ::></a>
<a class="btn btn-default" href="https://faucetbox.com/faucetinabox#update">Update instructions</a>
</span>
<:: changelog ::>
</div>
TEMPLATE;

$page_nav_template = <<<TEMPLATE
    <li><a href="#page-wrap-<:: i ::>" role="tab" data-toggle="tab">Page <:: i ::></a></li>
TEMPLATE;

$page_form_template = <<<TEMPLATE
<div class="page-wrap panel panel-default tab-pane" id="page-wrap-<:: i ::>">
    <div class="form-group">
        <label class="control-label" for="pages.<:: i ::>.name">Page name:</label>
        <input class="form-control" type="text" id="pages.<:: i ::>.name" name="pages[<:: i ::>][name]" value="<:: page_name ::>">
    </div>
    <div class="form-group">
        <label class="control-label" for="pages.<:: i ::>.html">HTML content:</label>
        <textarea class="form-control" id="pages.<:: i ::>.html" name="pages[<:: i ::>][html]"><:: html ::></textarea>
    </div>
    <button type="button" class="btn btn-sm pageDeleteButton" onclick="deletePage(this);">Delete this page</button>
</div>
TEMPLATE;

$connection_error_template = <<<TEMPLATE
<p class="alert alert-danger">Error connecting to <a href="https://faucetbox.com">FaucetBOX.com API</a>. Either your hosting provider doesn't support external connections or FaucetBOX.com API is down. Send an email to <a href="mailto:support@faucetbox.com">support@faucetbox.com</a> if you need help.</p>
TEMPLATE;

$reverse_proxy_changed_alert_template = <<<TEMPLATE
<p class="alert alert-danger"><b>This setting was automatically changed back to None, because people viewing your faucet without reverse proxy were detected</b>. Make sure your reverse proxy is configured correctly.</p>
TEMPLATE;

$curl_warning_template = <<<TEMPLATE
<p class="alert alert-danger">cURL based connection failed, using legacy method. Please set <code>\$disable_curl = true;</code> in <code>config.php</code> file.</p>
TEMPLATE;

$send_coins_success_template = <<<TEMPLATE
<p class="alert alert-success">You sent {{amount}} satoshi to <a href="https://faucetbox.com/check/{{address}}" target="_blank">{{address}}</a>.</p>
<script> $(document).ready(function(){ $('.nav-tabs a[href="#send-coins"]').tab('show'); }); </script>
TEMPLATE;

$send_coins_error_template = <<<TEMPLATE
<p class="alert alert-danger">There was an error while sending {{amount}} satoshi to "{{address}}": <u>{{error}}</u></p>
<script> $(document).ready(function(){ $('.nav-tabs a[href="#send-coins"]').tab('show'); }); </script>
TEMPLATE;
// ****************** END ADMIN TEMPLATES

#reCaptcha template
$recaptcha_template = <<<TEMPLATE
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="<:: your_site_key ::>"></div>
<noscript>
  <div style="width: 302px; height: 352px;">
    <div style="width: 302px; height: 352px; position: relative;">
      <div style="width: 302px; height: 352px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=<:: your_site_key ::>"
                frameborder="0" scrolling="no"
                style="width: 302px; height:352px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 250px; height: 80px; position: absolute; border-style: none;
                  bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 80px; border: 1px solid #c1c1c1;
                         margin: 0px; padding: 0px; resize: none;" value="">
        </textarea>
      </div>
    </div>
  </div>
</noscript>
TEMPLATE;

function setNewPass() {
    global $sql;
    $alphabet = str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890');
    $password = ''; 
    for($i = 0; $i < 15; $i++)
        $password .= $alphabet[array_rand($alphabet)];
    $hash = crypt($password);
    $sql->query("REPLACE INTO Faucetinabox_Settings VALUES ('password', '$hash')");
    return $password;
}

// check if configured
try {
    $pass = $sql->query("SELECT `value` FROM `Faucetinabox_Settings` WHERE `name` = 'password'")->fetch();
} catch(PDOException $e) {
    $pass = null;
}

function getIP() {
	global $sql;
	$type = $sql->query("SELECT `value` FROM `Faucetinabox_Settings` WHERE `name` = 'reverse_proxy'")->fetch();
	if (!$type) $type = array('none');
	switch ($type[0]) {
		case 'cloudflare':
			$ip = array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : null;
		break;
		case 'incapsula':
			$ip = array_key_exists('HTTP_INCAP_CLIENT_IP', $_SERVER) ? $_SERVER['HTTP_INCAP_CLIENT_IP'] : null;
		break;
		default:
			$ip = $_SERVER['REMOTE_ADDR'];
	}
    if (empty($ip)) {
        $sql->query("UPDATE `Faucetinabox_Settings` SET `value` = 'none-auto' WHERE `name` = 'reverse_proxy' AND `value` <> 'none' LIMIT 1");
        return $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function is_ssl(){
    if(isset($_SERVER['HTTPS'])){
        if('on' == strtolower($_SERVER['HTTPS']))
            return true;
        if('1' == $_SERVER['HTTPS'])
            return true;
        if(true == $_SERVER['HTTPS'])
            return true;
    }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])){
        return true;
    }
    if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
        return true;
    }
    return false;
}

if($pass) {
    if(array_key_exists('p', $_GET) && $_GET['p'] == 'logout')
        $_SESSION = array();

    // check db updates
    $dbversion = $sql->query("SELECT `value` FROM `Faucetinabox_Settings` WHERE `name` = 'version'")->fetch();
    if($dbversion) {
        $dbversion = intval($dbversion[0]);
    } else {
        $dbversion = -1;
    }
    foreach($db_updates as $v => $update) {
        if($v > $dbversion) {
            foreach($update as $query) {
                $sql->exec($query);
            }
        }
    }
    if($dbversion < 17) {
        // dogecoin changed from satoshi to doge
        // better clear rewards...
        $c = $sql->query("SELECT `value` FROM `Faucetinabox_Settings` WHERE `name` = 'currency'")->fetch();
        if($c[0] == 'DOGE')
            $sql->exec("UPDATE `Faucetinabox_Settings` SET `value` = '' WHERE name = 'rewards'");
    }
    if(intval($version) > intval($dbversion)) {
        $q = $sql->prepare("UPDATE `Faucetinabox_Settings` SET `value` = ? WHERE `name` = 'version'");
        $q->execute(array($version));
    }

    if(array_key_exists('p', $_GET) && $_GET['p'] == 'admin') {
        $invalid_key = false;
        if(array_key_exists('password', $_POST))
            if($pass[0] == crypt($_POST['password'], $pass[0]))
                $_SESSION["$session_prefix-logged_in"] = true;
        if(array_key_exists("$session_prefix-logged_in", $_SESSION)) {
            // logged in to admin page
            if(array_key_exists('get_options', $_POST)) {
                if(file_exists("templates/{$_POST["get_options"]}/setup.php")) {
                    require_once("templates/{$_POST["get_options"]}/setup.php");
                    die(getTemplateOptions($sql, $_POST['get_options']));
                } else {
                    die('<p>No template defined options available.</p>');
                }
            } else if(array_key_exists("reset", $_POST)) {
                $sql->exec("DELETE FROM Faucetinabox_Settings WHERE name NOT LIKE '%key%' AND name != 'password'");
                $sql->exec($default_data_query);
            }
            $q = $sql->prepare("SELECT value FROM Faucetinabox_Settings WHERE name = ?");
            $q->execute(array('apikey'));
            $apikey = $q->fetch();
            $apikey = $apikey[0];
            $q->execute(array('currency'));
            $currency = $q->fetch();
            $currency = $currency[0];
            $fb = new FaucetBOX($apikey, $currency, $connection_options);
            $currencies = $fb->getCurrencies();
            $connection_error = '';
            $curl_warning = '';
            if($fb->curl_warning) {
                $curl_warning = $curl_warning_template;
            }
            if(!$currencies) {
                $currencies = array('BTC', 'LTC', 'DOGE', 'PPC', 'XPM', 'DASH');
                if(!$fb->last_status) {
                    $connection_error = $connection_error_template;
                }
            }
            $send_coins_message = '';
            if(array_key_exists('send_coins', $_POST)) {
                
                $amount = array_key_exists('send_coins_amount', $_POST) ? intval($_POST['send_coins_amount']) : 0;
                $address = array_key_exists('send_coins_address', $_POST) ? trim($_POST['send_coins_address']) : '';

                $fb = new FaucetBOX($apikey, $currency, $connection_options);
                $ret = $fb->send($address, $amount);
                
                if ($ret['success']) {
                    $send_coins_message = str_replace(array('{{amount}}','{{address}}'), array($amount,$address), $send_coins_success_template);
                } else {
                    $send_coins_message = str_replace(array('{{amount}}','{{address}}','{{error}}'), array($amount,$address,$ret['message']), $send_coins_error_template);
                }
                    
            }
            if(array_key_exists('save_settings', $_POST)) {
                $currency = $_POST['currency'];
                $fb = new FaucetBOX($_POST['apikey'], $currency, $connection_options);
                $ret = $fb->getBalance();
                
                if($ret['status'] == 403) {
                    $invalid_key = true;
                } elseif($ret['status'] == 405) {
                    $sql->query("UPDATE Faucetinabox_Settings SET `value` = 0 WHERE name = 'balance'");
                } elseif(array_key_exists('balance', $ret)) {
                    $q = $sql->prepare("UPDATE Faucetinabox_Settings SET `value` = ? WHERE name = 'balance'");
                    if($currency != 'DOGE')
                        $q->execute(array($ret['balance']));
                    else
                        $q->execute(array($ret['balance_bitcoin']));
                }

                $q = $sql->prepare("INSERT IGNORE INTO Faucetinabox_Settings (`name`, `value`) VALUES (?, ?)");
                $template = $_POST["template"];
                preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents("templates/$template/index.php"), $matches);
                foreach($matches[2] as $box)
                    $q->execute(array("{$box}_$template", ''));
                
                $q = $sql->prepare("UPDATE Faucetinabox_Settings SET value = ? WHERE name = ?");
                $ipq = $sql->prepare("INSERT INTO Faucetinabox_Pages (url_name, name, html) VALUES (?, ?, ?)");
                $sql->exec("DELETE FROM Faucetinabox_Pages");
                foreach($_POST as $k => $v) {
                    if($k == 'apikey' && $invalid_key)
                        continue;
                    if($k == 'pages') {
                        foreach($_POST['pages'] as $p) {
                            $url_name = strtolower(preg_replace("/[^A-Za-z0-9_\-]/", '', $p["name"]));
                            $i = 0;
                            $success = false;
                            while(!$success) {
                                try {
                                    if($i)
                                        $ipq->execute(array($url_name.'-'.$i, $p['name'], $p['html']));
                                    else
                                        $ipq->execute(array($url_name, $p['name'], $p['html']));
                                    $success = true;
                                } catch(PDOException $e) {
                                    $i++;
                                }
                            }
                        }
                        continue;
                    }
                    $q->execute(array($v, $k));
                }
                if (!array_key_exists('reklamper_enabled', $_POST)) $q->execute(array('', 'reklamper_enabled'));
                
            }
            $page = str_replace('<:: content ::>', $admin_template, $master_template);
            $query = $sql->query("SELECT name, value FROM Faucetinabox_Settings");
            while($row = $query->fetch()) {
                if($row[0] == 'template') {
                    if(file_exists("templates/{$row[1]}/index.php")) {
                        $current_template = $row[1];
                    } else {
                        $templates = glob("templates/*");
                        if($templates)
                            $current_template = substr($templates[0], strlen('templates/'));
                        else
                            die(str_replace("<:: content ::>", "<div class='alert alert-danger' role='alert'>No templates found! Please reinstall your faucet.</div>", $master_template));
                    }
                } else {
                    if ($row[0] == 'reverse_proxy') {
                        if ($row[1] == 'none-auto') {
                            $reverse_proxy_changed_alert = $reverse_proxy_changed_alert_template;
                            $row[1] = 'none';
                        } else {
                            $reverse_proxy_changed_alert = '';
                        }
                        $page = str_replace('<:: reverse_proxy_changed_alert ::>', $reverse_proxy_changed_alert, $page);
                    }
                    if ($row[0] == 'reklamper_enabled') {
                        $row[1] = $row[1] == 'on' ? 'checked' : '';
                    }
                    $page = str_replace("<:: {$row[0]} ::>", $row[1], $page);
                }
            }


            $templates = '';
            foreach(glob("templates/*") as $template) {
                $template = basename($template);
                if($template == $current_template) {
                    $templates .= "<option selected>$template</option>";
                } else {
                    $templates .= "<option>$template</option>";
                }
            }
            $page = str_replace('<:: templates ::>', $templates, $page);
            $page = str_replace('<:: current_template ::>', $current_template, $page);

            
            if(file_exists("templates/{$current_template}/setup.php")) {
                require_once("templates/{$current_template}/setup.php");
                $page = str_replace('<:: template_options ::>', getTemplateOptions($sql, $current_template), $page);
            } else {
                $page = str_replace('<:: template_options ::>', '<p>No template defined options available.</p>', $page);
            }

            $q = $sql->query("SELECT name, html FROM Faucetinabox_Pages ORDER BY id");
            $pages = '';
            $pages_nav = '';
            $i = 1;
            while($userpage = $q->fetch()) {
                $html = htmlspecialchars($userpage['html']);
                $name = htmlspecialchars($userpage['name']);
                $pages .= str_replace(array('<:: i ::>', '<:: page_name ::>', '<:: html ::>'),
                                      array($i, $name, $html), $page_form_template);
                $pages_nav .= str_replace('<:: i ::>', $i, $page_nav_template);
                ++$i;
            }
            $page = str_replace('<:: pages ::>', $pages, $page);
            $page = str_replace('<:: pages_nav ::>', $pages_nav, $page);
            $currencies_select = "";
            foreach($currencies as $c) {
                if($currency == $c)
                    $currencies_select .= "<option value='$c' selected>$c</option>";
                else
                    $currencies_select .= "<option value='$c'>$c</option>";
            }
            $page = str_replace('<:: currency ::>', $currency, $page);
            $page = str_replace('<:: currencies ::>', $currencies_select, $page);


            if($invalid_key)
                $page = str_replace('<:: invalid_key ::>', $invalid_key_error_template, $page);
            else
                $page = str_replace('<:: invalid_key ::>', '', $page);

            $page = str_replace('<:: page_form_template ::>', 
                                str_replace(array("\n", '"'), array('', '\"'), $page_form_template), 
                                $page);
            $page = str_replace('<:: page_nav_template ::>', 
                                str_replace(array("\n", '"'), array('', '\"'), $page_nav_template), 
                                $page);

            $response = $fb->fiabVersionCheck();

            if(!$connection_error && $response['version'] && $version < intval($response["version"])) {
                $page = str_replace('<:: version_check ::>', $new_version_template, $page);
                $changelog = '';
                foreach($response['changelog'] as $v => $changes) {
                    if(intval($v) > $version) {
                        $changelog .= "<p>Changes in r$v: $changes</p>";
                    }
                }
                $page = str_replace(array('<:: url ::>', '<:: version ::>', '<:: changelog ::>'), array($response['url'], $response['version'], $changelog), $page);
            } else {
                $page = str_replace('<:: version_check ::>', '', $page);
            }
            $page = str_replace('<:: connection_error ::>', $connection_error, $page);
            $page = str_replace('<:: curl_warning ::>', $curl_warning, $page);
            $page = str_replace('<:: send_coins_message ::>', $send_coins_message, $page);
            die($page);
        } else {
            // requested admin page without session
            $page = str_replace('<:: content ::>', $admin_login_template, $master_template);
            die($page);
        }
    } elseif(array_key_exists('p', $_GET) && $_GET['p'] == 'password-reset') {
        $error = "";
        if(array_key_exists('dbpass', $_POST)) {
            if($_POST['dbpass'] == $dbpass) {
                $password = setNewPass();
                $page = str_replace('<:: content ::>', $pass_template, $master_template);
                $page = str_replace('<:: password ::>', $password, $page);
                die($page);
            } else {
                $error = "<p class='alert alert-danger' role='alert'>Wrong database password</p>";
