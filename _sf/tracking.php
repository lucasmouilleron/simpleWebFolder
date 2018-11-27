<?php

/////////////////////////////////////////////////////////////////////////////
/// SETUP
/////////////////////////////////////////////////////////////////////////////
$rootURL = getRootURL();
$docRoot = getDocRoot();
$rootFolder = realpath(__DIR__ . "/..");
$baseURL = getBaseURL($docRoot);
$alerts = [];
$isAdmin = false;
$wantAdmin = false;
$item = null;
$password = null;
$maxItems = 500;

/////////////////////////////////////////////////////////////////////////////
/// ENABLED
/////////////////////////////////////////////////////////////////////////////
if(!$TRACKING_ENABLED)
{
    array_push($alerts, ["Can't track", "Tracking is not enabled."]);
}

/////////////////////////////////////////////////////////////////////////////
/// ADMIN
/////////////////////////////////////////////////////////////////////////////
if(isset($_POST["admin-password-submit"])) setAdminPassword($_POST["password"]);
$isAdmin = getAdminPassword() == $ADMIN_PASSWORD;
if(isset($_POST["admin-password-submit"]) && !$isAdmin) array_push($alerts, ["Can't authenticate as admin", "Can't authenticate as admin. The password you provided is incorrect."]);
if(isset($_GET["admin"])) $wantAdmin = true;

/////////////////////////////////////////////////////////////////////////////
if(isset($_POST["filter"]))
{
    if(isset($_POST["password"]) && $_POST["password"] != "") $password = $_POST["password"];
    if(isset($_POST["item"]) && $_POST["item"] != "") $item = $_POST["item"];
    if(isset($_POST["maxItems"]) && $_POST["maxItems"] != "") $maxItems = $_POST["maxItems"];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $NAME; ?> - <?php echo $currentPage; ?></title>
    <script src="<?php echo $baseURL; ?>_sf_assets/jquery.js"></script>
    <script src="<?php echo $baseURL; ?>_sf_assets/stupidtable.js"></script>
    <script src="<?php echo $baseURL; ?>_sf_assets/clipboard.js"></script>
    <script src="<?php echo $baseURL; ?>_sf_assets/tooltipstr.js"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/tooltipster.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/tooltipster-theme.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/style.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_overrides/style.css">
</head>
<body>

<div class="header">
    <a href="<?php echo $rootURL . $baseURL . "tracking"; ?>">
        <div class="logo"></div>
    </a>
</div>

<div class="name"><a href="<?php echo $rootURL . $baseURL . "tracking"; ?>"><?php echo $NAME; ?></a></div>

<div class="navigation section">
    <?php if($isAdmin): ?>
        <div data-toggle="tooltip" title="Leave admin mode"><a href="<?php echo $baseURL . "?noadmin"; ?>"><i class="icon <?php echo $ICON_LEAVE_ADMIN_CLASS; ?>"></i></a></div>
        <div class="files" data-toggle="tooltip" title="Files"><a href="<?php echo $baseURL; ?>" target="_files"><i class="icon <?php echo $ICON_CURRENT_FOLDER_CLASS; ?>"></i></a></div>
        <?php if($SHARING_ENABLED): ?>
            <div class="shares" data-toggle="tooltip" title="Shares management"><a href="<?php echo $baseURL . "shares"; ?>" target="_shares"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a></div>
        <?php endif; ?>
        <?php if($TRACKING_ENABLED): ?>
            <div class="tracking" data-toggle="tooltip" title="Tracking"><a href="<?php echo $baseURL . "tracking"; ?>" target="_tracking"><i class="icon <?php echo $ICON_TRACKING_CLASS; ?>"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>


<?php foreach($alerts as $alert): ?>
    <div class="alert">
        <h2><?php echo $alert[0]; ?></h2>
        <p><?php echo $alert[1]; ?></p>
    </div>
<?php endforeach; ?>

<?php if(!$isAdmin): ?>
    <div class="authenticate section">
        <div class="section-title">Admin, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="password"/>
            <input type="submit" name="admin-password-submit" value="login"/>
        </form>
    </div>
<?php else: ?>
    <div class="trackings-filter section">
        <div class="section-title">Filter</div>
        <form method="post" class="inline">
            <label>Limit</label><select name="maxItems">
                <option <?php if($maxItems == 500) echo "SELECTED"; ?>>500</option>
                <option <?php if($maxItems == 1000) echo "SELECTED"; ?>>1000</option>
                <option <?php if($maxItems == "all") echo "SELECTED"; ?>>all</option>
            </select>
            <label></label><input type="text" name="password" value="<?php echo $password; ?>" placeholder="Password" style="width:200px;"/>
            <label></label><input type="text" name="item" value="<?php echo $item; ?>" placeholder="Item" style="width:200px;"/>
            <label></label><input type="submit" name="filter" value="Filter" style="width:100px;"/>
        </form>
    </div>

    <?php $trackings = getTrackings($rootFolder, $password, $item, $maxItems); ?>
    <div class="trackings section">
        <div class="section-title">Trackings</div>
        <table>
            <thead>
            <tr>
                <th data-sort="string-ins">Item</th>
                <th data-sort="string-ins" width="200">Password</th>
                <th data-sort="string-ins" width="100">Authorized</th>
                <th data-sort="string-ins" width="140">IP</th>
                <th data-sort="string-ins" width="160">Date</th>
            </tr>
            </thead>
            <tbody>
            <?php $i = 0; ?>
            <?php foreach($trackings as $tracking): ?>
                <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                    <td><?php echo $tracking->item; ?></td>
                    <td><?php echo $tracking->password; ?></td>
                    <td><?php echo $tracking->authorized; ?></td>
                    <td><?php echo $tracking->ip; ?></td>
                    <td><?php echo date("Ymd @ H:i", $tracking->date); ?></td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<div class="footer"><?php echo $NAME; ?> - <?php echo $CREDITS; ?></div>

<script>
    $(document).ready(function () {
        window.name = "_tracking";


        $('[data-toggle="tooltip"]').tooltipster({theme: "tooltipster-borderless", animationDuration: 200, delay: 20, side: "bottom"});
        var table = $("table").stupidtable();
        table.bind("aftertablesort", function (event, data) {
            var tableElt = data.$th.parent().parent().parent();
            tableElt.find("tr:even").addClass("even");
            tableElt.find("tr:odd").removeClass("even");
        });
    });
</script>

</body>
</html>