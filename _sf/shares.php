<?php

/////////////////////////////////////////////////////////////////////////////
/// SETUP
/////////////////////////////////////////////////////////////////////////////
$rootURL = getRootURL();
$docRoot = getDocRoot();
$rootFolder = realpath(__DIR__ . "/..");
$baseURL = getBaseURL($docRoot);
$alerts = [];
$share = null;
$shares = [];
$addShareFile = null;
$addShareIsDir = false;
$isAdmin = false;
$sharesFolder = $rootFolder . "/_sf_shares";
$needForce = false;
$shareID = null;
$shareDuration = "";
$defaultShareID = uniqid();
$maxShares = 50;
$shareCreated = false;
$filterShareID = null;

/////////////////////////////////////////////////////////////////////////////
/// ENABLED
/////////////////////////////////////////////////////////////////////////////
if(!$SHARING_ENABLED)
{
    array_push($alerts, ["Can't share", "Sharing is not enabled."]);
}

/////////////////////////////////////////////////////////////////////////////
/// ADMIN
/////////////////////////////////////////////////////////////////////////////
if(isset($_POST["admin-password-submit"])) setAdminPassword($_POST["password"]);
$isAdmin = getAdminPassword() == $ADMIN_PASSWORD;
if(isset($_POST["admin-password-submit"]) && !$isAdmin) array_push($alerts, ["Can't authenticate as admin", "Can't authenticate as admin. The password you provided is incorrect."]);


/////////////////////////////////////////////////////////////////////////////
/// SHARE CREATE
/////////////////////////////////////////////////////////////////////////////
if($SHARING_ENABLED && $isAdmin && startsWith($currentPage, "/create-share="))
{
    $addShareFile = str_replace("/create-share=", "", $currentPage);
    $addShareIsDir = is_dir($rootFolder . $addShareFile);
    if(isset($_POST["create-share-submit"]) || isset($_POST["create-share-force-submit"]))
    {
        if(!isset($_POST["shareID"])) array_push($alerts, ["Can't create share", "No share ID provided."]);
        else
        {
            $shareDuration = floatval($_POST["duration"]);
            $shareID = $_POST["shareID"];
            if($shareID == "") $shareID = $_POST["defaultShareID"];
            $shareID = clean($shareID);
            if($shareID == "") array_push($alerts, ["Can't create share", "Share ID provided is invalid."]);
            else
            {
                $share = getShare($sharesFolder, $shareID);
                if(!isset($share) || $_POST["create-share-force-submit"])
                {
                    $share = createShare($sharesFolder, $shareID, $addShareFile, $shareDuration, @$_POST["password"]);
                    $addShareFile = null;
                    $shareID = null;
                    $shareURL = myurlencode($rootURL . $baseURL . "share=" . $share->ID);
                    $shareCreated = true;
                }
                else
                {
                    array_push($alerts, ["Can't create share", "The share ID " . $share->ID . " is alread used for " . $share->file]);
                    $needForce = true;
                }
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////
/// SHARE DELETE
/////////////////////////////////////////////////////////////////////////////
if($SHARING_ENABLED && $isAdmin && startsWith($currentPage, "/remove-share="))
{
    $shareID = str_replace("/remove-share=", "", $currentPage);
    if($shareID == "") array_push($alerts, ["Can't remove share", "Share ID provided is invalid"]);
    else
    {
        removeShare($sharesFolder, $shareID);
        array_push($alerts, ["Share removed", "Share " . $shareID . " has been removed and is no longer available."]);
    }
}

/////////////////////////////////////////////////////////////////////////////
/// SHARES DETAILS
/////////////////////////////////////////////////////////////////////////////
if($SHARING_ENABLED && $isAdmin)
{
    if(isset($_POST["filter-share-submit"]))
    {
        if($_POST["shareID"] != "") $filterShareID = $_POST["shareID"];
        if(isset($filterShareID) and strContains("share=", $filterShareID)) $filterShareID = str_replace($rootURL . $baseURL . "share=", "", $filterShareID);
    }
    $shares = getShares($sharesFolder, $maxShares, $filterShareID);
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
    <a href="<?php echo $rootURL . $baseURL . "shares"; ?>">
        <div class="logo"></div>
    </a>
</div>

<div class="name"><a href="<?php echo $rootURL . $baseURL . "shares"; ?>"><?php echo $NAME; ?></a></div>

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

    <?php if($shareCreated): ?>
        <div class="alert">
            <h2>Share created</h2>
            <p>Share created for <?php echo $share->file; ?> @ <?php echo $shareURL; ?></p>
            <p><a class="link" data-clipboard-text="<?php echo $shareURL; ?>" data-toggle="tooltip" title="Copy link">Copy share link</a> | <a href="<?php echo $baseURL . "share=" . $share->ID; ?>" target="_share_<?php echo $share->ID; ?>">View share</a></p>
        </div>
    <?php endif; ?>

    <div class="readme section">
        <div class="readme-content">
            Add shares from file browsing @ <a href="<?php echo $rootURL . $baseURL; ?>" target="_files"><?php echo $rootURL . $baseURL; ?></a>
        </div>
    </div>
    <?php if(isset($addShareFile)): ?>
        <div class="create-share section">
            <div class="section-title">Create share</div>
            <?php if($addShareIsDir): ?>
                <div>Warning: You are creating a share on a folder. All sub files and folders of <i><?php echo $addShareFile; ?></i> will be accessible from this share.</div>
            <?php endif; ?>
            <form method="post">
                <input readonly type="text" placeholder="<?php echo $addShareFile; ?>"/>
                <input type="text" name="shareID" placeholder="Share ID* (default: <?php echo $defaultShareID; ?>)" value="<?php echo $shareID; ?>"/>
                <input type="hidden" name="defaultShareID" value="<?php echo $defaultShareID; ?>"/>
                <input type="text" name="duration" placeholder="Duration in days" value="<?php echo $shareDuration; ?>"/>
                <input type="password" name="password" placeholder="Password" value=""/>
                <?php if($needForce): ?><input type="submit" name="create-share-force-submit" value="Override share"/>
                <?php else: ?> <input type="submit" name="create-share-submit" value="Create share"/> <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <div class="readme section">
        <div class="readme-content">
            <div class="section-title">Share lookup</div>
            <form method="post" class="inline">
                <input type="text" name="shareID" value="<?php echo $filterShareID; ?>" placeholder="share (partial) ID or share url"/>
                <label></label><input type="submit" name="filter-share-submit" value="Filter" style="width:100px;"/>
            </form>
        </div>
    </div>
    <div class="shares section">
        <?php if(isset($filterShareID)): ?>
            <div class="section-title">Shares found</div>
        <?php else: ?>
            <div class="section-title">Latest <?php echo $maxShares; ?> shares</div>
        <?php endif; ?>
        <?php if(count($shares) > 0): ?>
            <table>
                <thead>
                <tr>
                    <th data-sort="string-ins">ID</th>
                    <th data-sort="string-ins">File</th>
                    <th data-sort="string-ins" width="160">Expiration</th>
                    <th data-sort="string-ins">Password</th>
                    <th data-sort="int" width="50"># views</th>
                    <th data-sort="string-ins" width="160">Latest</th>
                    <th width="70">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($shares as $share): ?>
                    <?php $shareURL = $rootURL . $baseURL . "share=" . $share->ID; ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td><?php echo $share->ID; ?></td>
                        <td onclick="window.open('<?php echo cleanURL($rootURL . $baseURL . $share->file); ?>')"><a><?php echo $share->file; ?></a></td>
                        <td><?php echo getShareExpirationString($share) ?></td>
                        <td><?php echo $share->password; ?></td>
                        <td><?php echo count($share->views); ?></td>
                        <td>
                            <?php if(count($share->views) > 0): ?>
                                <?php echo date("Ymd @ H:i", $share->views[count($share->views) - 1]->date); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="link" data-clipboard-text="<?php echo $shareURL; ?>" data-toggle="tooltip" title="Copy link"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a>
                            <a data-toggle="tooltip" title="Details" href="<?php echo $baseURL . "share=" . $share->ID; ?>" target="_share_<?php echo $share->ID; ?>"><i class="icon <?php echo $ICON_DETAIL_CLASS; ?>"></i></a>
                            <a data-toggle="tooltip" title="Remove" class="confirmation" href="<?php echo cleanURL($baseURL . "remove-share=" . $share->ID); ?>"><i class="icon <?php echo $ICON_DELETE_CLASS; ?>"></i></a>
                        </td>
                    </tr>
                    <?php $i++; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            No shares.
        <?php endif; ?>
    </div>

<?php endif; ?>

<div class="footer"><?php echo $NAME; ?> - <?php echo $CREDITS; ?></div>

<script>
    $(document).ready(function () {
        window.name = "_shares";

        var clipboard = new ClipboardJS(".link");
        clipboard.on('success', function (e) {
            alert("Link " + e.text + " copied to clipboard")
        });

        $('.confirmation').on('click', function () {
            return confirm('Are you sure?');
        });

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