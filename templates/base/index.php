<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $data["name"]; ?></title>
<style>
#left, #right {
    margin: 0;
    width: 25%;
    float: left;
}

#right {
    text-align: right;
}

#center {
    width: 50%;
    margin: 0;
    float: left;
    text-align: center;
}

#recaptcha_area {
    margin: 0 auto;
}

#captchme_widget_div{
margin: 0 auto;
width: 315px;
}

.g-recaptcha{
width: 304px;
margin: 0 auto;
}

#adcopy-outer {
    margin: 0 auto !important;
}

.reklamper-widget-holder{
margin: auto;
}
</style>
</head>
<body>
    <div id="left">
        <ul>
        <?php foreach($data["user_pages"] as $page): ?>
            <li><a href="?p=<?php echo $page["url_name"]; ?>"><?php echo $page["name"]; ?></a></li>
        <?php endforeach; ?>
        </ul>
        <?php echo $data["custom_left_ad_slot"]; ?>
        <p>Possible rewards: <?php echo $data["rewards"]; ?></p>
    </div>
        <div id="center">
        <h1><?php echo $data["name"]; ?></h1>
        <h2><?php echo $data["short"]; ?></h2>
        <p>Balance: <?php echo $data["balance"]." ".$data["unit"]; ?></p>
        <?php if($data["error"]) echo $data["error"]; ?>
        <?php switch($data["page"]): 
                case "disabled": ?>
            FAUCET DISABLED. Go to <a href="?p=admin">admin page</a> and fill all required data!
        <?php break; case "paid":
                echo $data["paid"];
              break; case "eligible": ?>
            <form method="POST">
                <div>
                    <?php if(!$data["captcha_valid"]): ?>
                    <p class="alert alert-danger" role="alert">Invalid Captcha!</p>
                    <?php endif; ?>
                </div>
                <div>
                <label for="address">Your address:</label> <input type="text" name="address" class="form-control" value="<?php echo $data["address"]; ?>">
                </div>
                <div>
                    <?php echo $data["captcha"]; ?>
                    <div class="text-center">
                    <?php
                    if (count($data['captcha_info']['available']) > 1) {
                        foreach ($data['captcha_info']['available'] as $c) {
                            if ($c == $data['captcha_info']['selected']) {
                                echo '<b>' .$c. '</b> ';
                            } else {
                                echo '<a href="?cc='.$c.'">'.$c.'</a> ';
                            }
                        }
                    }
                    ?>
                    </div>
                </div>
                <div>
                    <input type="submit" class="btn btn-primary btn-lg" value="Get reward!">
                </div>
            </form>
        <?php break; case "visit_later": ?>
            <p>You have to wait <?php echo $data["time_left"]; ?></p>
        <?php break; case "user_page": ?>
        <?php echo $data["user_page"]["html"]; ?>
        <?php break; endswitch; ?>
    </div>
    <div id="right">
        <?php echo $data["custom_right_ad_slot"]; ?>
        <?php if($data["referral"]): ?>
        <p>
        Referral commission: <?php echo $data["referral"]; ?>%<br>
        Reflink:<br>
        <code><?php echo $data["reflink"]; ?></code>
        </p>
        <?php endif; ?>
    </div>
</body>
</html>
