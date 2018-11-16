<?php

/////////////////////////////////////////////////////////////////////////////
$rootURL = getRootURL();
$docRoot = getDocRoot();
$baseURL = getBaseURL($docRoot);
$share = null;
$shares = [];
$addShareFile = null;
$isAdmin = false;
$sharesFolder = $rootFolder . "/_sf_shares";
$needForce = false;
$shareID = null;
$shareDuration = "";

/////////////////////////////////////////////////////////////////////////////
/// ADMIN
/////////////////////////////////////////////////////////////////////////////
if(isset($_POST["admin-password-submit"])) setAdminPassword($_POST["password"]);
$isAdmin = getAdminPassword() == $ADMIN_PASSWORD;
if(isset($_POST["admin-password-submit"]) && !$isAdmin) array_push($alerts, ["Can't authenticate as admin", "Can't authenticate as admin. The password you provided is incorrect."]);

/////////////////////////////////////////////////////////////////////////////
/// SHARE CREATE
/////////////////////////////////////////////////////////////////////////////
if($isAdmin && startsWith($currentPage, "/create-share="))
{
    $addShareFile = str_replace("/create-share=", "", $currentPage);
    if(isset($_POST["create-share-submit"]) || isset($_POST["create-share-force-submit"]))
    {
        if(!isset($_POST["shareID"])) array_push($alerts, ["Can't create share", "No share ID provided."]);
        else
        {
            $shareID = clean($_POST["shareID"]);
            $shareDuration = floatval($_POST["duration"]);
            if($shareID == "") array_push($alerts, ["Can't create share", "Share ID provided is invalid."]);
            else
            {
                $share = getShare($sharesFolder, $shareID);
                if(!isset($share) || $_POST["create-share-force-submit"])
                {
                    $share = createShare($sharesFolder, $shareID, $addShareFile, $shareDuration);
                    $addShareFile = null;
                    $shareID = null;
                    $shareURL = $rootURL . $baseURL . "share=" . $share->ID;
                    array_push($alerts, ["Share created", "Share created for " . $share->file . " @ " . $shareURL]);
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
if($isAdmin && startsWith($currentPage, "/remove-share="))
{
    $shareID = str_replace("/remove-share=", "", $currentPage);
    if($shareID == "") array_push($alerts, ["Can't remove share", "Share ID provided is invalid"]);
    removeShare($sharesFolder, $shareID);
    array_push($alerts, ["Share removed", "Share " . $shareID . " has been removed and is no longer available."]);
}

/////////////////////////////////////////////////////////////////////////////
/// SHARES DETAILS
/////////////////////////////////////////////////////////////////////////////
if($isAdmin)
{
    if(isset($_GET["share"])) $share = getShare($sharesFolder, $_GET["share"]);
    $shares = getShares($sharesFolder);
//    if(count($shares) == 0) array_push($alerts, ["No shares", "No shares available. Add shares from file browsing @ <a href='" . $rootURL . $baseURL . "'>" . $rootURL . $baseURL . "</a>"]);
}

/////////////////////////////////////////////////////////////////////////////
/// SHARE DETAILS
/////////////////////////////////////////////////////////////////////////////
if($isAdmin && startsWith($currentPage, "/share="))
{
    $shareID = str_replace("/share=", "", $currentPage);
    list($success, $hint) = getShareAndDownload($rootFolder, "_sf_shares", $shareID);
    if(!$success) array_push($alerts, ["Can't get file", "The file you have requested is not available: " . $hint . "."]);
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
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.10/css/all.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/tooltipster.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/tooltipster-theme.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_assets/style.css">
    <link rel="stylesheet" href="<?php echo $baseURL; ?>_sf_overrides/style.css">
</head>
<body>

<div class="header">
    <a href="<?php echo $baseURL; ?>">
        <div class="logo"></div>
    </a>
</div>

<div class="name"><a href="<?php echo $baseURL; ?>"><?php echo $NAME; ?></a></div>


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
    <div class="readme section">
        <div class="readme-content">
            Add shares from file browsing @ <a href="<?php echo $rootURL . $baseURL; ?>"><?php echo $rootURL . $baseURL; ?></a>
        </div>
    </div>

    <?php if(isset($addShareFile)): ?>
        <div class="create-share section">
            <div class="section-title">Create share</div>
            <form method="post">
                <input readonly type="text" placeholder="<?php echo $addShareFile; ?>"/>
                <input type="text" name="shareID" placeholder="Share ID*" value="<?php echo $shareID; ?>"/>
                <input type="text" name="duration" placeholder="Duration in days" value="<?php echo $shareDuration; ?>"/>
                <?php if($needForce): ?><input type="submit" name="create-share-force-submit" value="Override share"/>
                <?php else: ?> <input type="submit" name="create-share-submit" value="Create share"/> <?php endif; ?>

            </form>
        </div>
    <?php endif; ?>
    <?php if(isset($share)): ?>
        <div class="share section">
            <div class="section-title">Share <?php echo $share->ID; ?> - <?php echo count($share->views); ?> views</div>
            <table>
                <thead>
                <tr>
                    <th data-sort="string-ins">IP</th>
                    <th data-sort="string-ins">Date</th>
                </tr>
                </thead>
                <?php $i = 0; ?>
                <?php if(count($share->views) == 0): ?>
                    <tr>
                        <td colspan="20">
                            <center>No views yet</center>
                        </td>
                    </tr><?php endif; ?>
                <?php foreach($share->views as $view): ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td><?php echo $view->ip; ?></td>
                        <td><?php echo date("Ymd @ H:i", $view->date); ?></td>
                    </tr>
                    <?php $i++; ?>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    <?php if(count($shares) > 0): ?>
        <div class="shares section">
            <div class="section-title">Latest shares</div>
            <table>
                <thead>
                <tr>
                    <th data-sort="string-ins">ID</th>
                    <th data-sort="string-ins">Link</th>
                    <th data-sort="string-ins">File</th>
                    <th data-sort="string-ins">Expiration</th>
                    <th data-sort="int"># views</th>
                    <th data-sort="string-ins">Latest</th>
                    <th width="70">Actions</th>
                </tr>
                </thead>
                <?php $i = 0; ?>
                <?php foreach($shares as $share): ?>
                    <?php $shareURL = $rootURL . $baseURL . "share=" . $share->ID; ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td><?php echo $share->ID; ?></td>
                        <td onclick="window.open('<?php echo $shareURL; ?>')"><a><?php echo $shareURL ?></a></td>
                        <td onclick="window.open('<?php echo $rootURL . $baseURL . $share->file; ?>')"><a><?php echo $share->file; ?></a></td>
                        <td><?php echo getShareExpirationString($share) ?></td>
                        <td><?php echo count($share->views); ?></td>
                        <td>
                            <?php if(count($share->views) > 0): ?>
                                <?php echo date("Ymd @ H:i", $share->views[count($share->views) - 1]->date); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="link" data-clipboard-text="<?php echo $shareURL; ?>" data-toggle="tooltip" title="Copy link"><i class="icon <?php echo $LINK_FOLDER_CLASS; ?>"></i></a>
                            <a data-toggle="tooltip" title="Details" href="<?php echo $baseURL . "shares?share=" . $share->ID; ?>"><i class="icon <?php echo $DETAIL_CLASS; ?>"></i></a>
                            <a data-toggle="tooltip" title="Remove" class="confirmation" href="<?php echo $baseURL . "remove-share=" . $share->ID; ?>"><i class="icon <?php echo $DELETE_CLASS; ?>"></i></a>
                        </td>
                    </tr>
                    <?php $i++; ?>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<script>
    $(document).ready(function () {
        var clipboard = new ClipboardJS(".link");
        clipboard.on('success', function (e) {
            alert("Link copied to clipboard")
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