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
$addShareFile = null;
$isAdmin = false;
$sharesFolder = $rootFolder . "/_sf_shares";
$shareID = null;
$isUserAuthorized = false;
$wantAdmin = false;
$relativePath = "";
$readmeContent = "";
$currentPage = getCurrentPage($rootFolder, $baseURL);

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
if(isset($_GET["admin"])) $wantAdmin = true;

/////////////////////////////////////////////////////////////////////////////
/// SHARE DETAILS
/////////////////////////////////////////////////////////////////////////////
if($SHARING_ENABLED)
{
    preg_match("/\/share=([^\/]*)\/?/", $currentPage, $matches);
    if(count($matches) == 2)
    {
        $shareID = $matches[1];
        $relativePath = str_replace("/share=" . $shareID, "", $currentPage);
        $shareURL = $baseURL . "share=" . $shareID . "/";
    }
    if(isset($_POST["password-submit"]) && isset($shareID)) setPasswordShare($shareID, $_POST["password"]);
    if(isset($shareID) && (!$wantAdmin || $isAdmin))
    {
        if($isAdmin) list($share, $success, $hint, $isUserAuthorized) = [getShare($sharesFolder, $shareID), true, "none", true];
        else list($success, $hint, $isUserAuthorized, $share) = getShareAsUser($rootFolder, $sharesFolder, $shareID, $relativePath);
        if($success)
        {
            $shareFileOrFolder = $rootFolder . $share->file;
            if($relativePath != "") $shareFileOrFolder = $shareFileOrFolder . $relativePath;
            if(!file_exists($shareFileOrFolder)) array_push($alerts, ["File not found", "The file " . $share->file . $relativePath . " does not exist."]);
            elseif(is_file($shareFileOrFolder))
            {
                if(!displayFile($shareFileOrFolder, $FORBIDEN_ITEMS)) array_push($alerts, ["File not found", "The file " . $shareFileOrFolder . " does not exist."]);
            }
            else
            {
                $items = scanFolder($shareFileOrFolder, $FORBIDEN_ITEMS, false);
                $readmeContent = getReadme($shareFileOrFolder, true);
            }
        }
        else array_push($alerts, ["Can't get file", "The file you have requested is not available: " . $hint . "."]);
    }
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
    <a href="<?php echo $shareURL; ?>">
        <div class="logo"></div>
    </a>
</div>

<div class="name"><a href="<?php echo $shareURL; ?>"><?php echo $NAME; ?></a></div>

<div class="navigation section">
    <div class="parent" data-toggle="tooltip" title="Go to parent folder"><?php if($relativePath != ""): ?><a href="<?php echo cleanURL($baseURL . $currentPage . "/.."); ?>"><i class="icon <?php echo $ICON_PARENT_FOLDER_CLASS; ?>"></i></a><?php else: ?>.<?php endif; ?></div>
    <?php if($isAdmin): ?>
        <div class="sep">|</div>
        <div data-toggle="tooltip" title="Leave admin mode"><a href="<?php echo $baseURL . "?noadmin"; ?>"><i class="icon <?php echo $ICON_LEAVE_ADMIN_CLASS; ?>"></i></a></div>
        <div class="files" data-toggle="tooltip" title="Files"><a href="<?php echo $baseURL; ?>" target="_files"><i class="icon <?php echo $ICON_CURRENT_FOLDER_CLASS; ?>"></i></a></div>
        <?php if($SHARING_ENABLED): ?>
            <div class="shares" data-toggle="tooltip" title="Shares management"><a href="<?php echo $baseURL . "shares"; ?>" target="_shares"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a></div>
        <?php endif; ?>
        <?php if($TRACKING_PASSWORD_ENABLED): ?>
            <div class="tracking" data-toggle="tooltip" title="Tracking"><a href="<?php echo $baseURL . "tracking"; ?>" target="_tracking"><i class="icon <?php echo $ICON_TRACKING_CLASS; ?>"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="page"><?php echo $currentPage; ?></div>
</div>

<?php foreach($alerts as $alert): ?>
    <div class="alert">
        <h2><?php echo $alert[0]; ?></h2>
        <p><?php echo $alert[1]; ?></p>
    </div>
<?php endforeach; ?>

<?php if($wantAdmin && !$isAdmin): ?>
    <div class="authenticate section">
        <div class="section-title">Admin, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="password"/>
            <input type="submit" name="admin-password-submit" value="login"/>
        </form>
    </div>
<?php endif; ?>

<?php if($isAdmin && isset($share)): ?>
    <div class="share section">
        <div class="section-title">Share <?php echo $share->ID; ?></div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Link</th>
                <th>File</th>
                <th># views</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?php echo $share->ID; ?></td>
                <td><a href="<?php echo $rootURL . $baseURL . "share=" . $share->ID; ?>" target="_share_<?php echo $share->ID; ?>"><?php echo $rootURL . $baseURL . "share=" . $share->ID; ?></a></td>
                <td><a href="<?php echo $rootURL . $baseURL . $share->file; ?>" target="_files"><?php echo $share->file; ?></a></td>
                <td><?php echo count($share->views); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="share section">
        <div class="section-title">Share <?php echo $share->ID; ?> views</div>
        <table>
            <thead>
            <tr>
                <th data-sort="string-ins" width="200">IP</th>
                <th data-sort="string-ins" width="160">Date</th>
                <th data-sort="string-ins">Item</th>
            </tr>
            </thead>
            <tbody>
            <?php $i = 0; ?>
            <?php if(count($share->views) == 0): ?>
                <tr>
                    <td colspan="20">
                        <center>No views yet</center>
                    </td>
                </tr><?php endif; ?>
            <?php foreach(array_reverse($share->views) as $view): ?>
                <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                    <td><?php echo $view->ip; ?></td>
                    <td><?php echo date("Ymd @ H:i", $view->date); ?></td>
                    <td><?php echo $view->item; ?></td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<?php if(!$isAdmin && !$wantAdmin && !$isUserAuthorized): ?>
    <div class="authenticate section">
        <div class="section-title">Protected area, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="Password"/>
            <input type="submit" name="password-submit" value="Login"/>
        </form>
    </div>
<?php endif; ?>

<?php if($isUserAuthorized && isset($items)): ?>

    <?php if($readmeContent != ""): ?>
        <div class="readme section">
            <div class="readme-content"><?php echo $readmeContent ?></div>
        </div>
    <?php endif; ?>

    <?php if(count($items["folders"]) > 0): ?>
        <div class="folders section">
            <div class="section-title">Folders</div>
            <table class="noselect">
                <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th data-sort="string-ins">Name</th>
                    <th data-sort="string-ins" style="width:20%;">Last modified</th>
                    <th data-sort="int" style="width:10%;"># items</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["folders"] as $item => $itemPath): ?>
                    <tr onclick="location.href='<?php echo cleanURL($shareURL . $relativePath . "/" . $item); ?>'" class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td class="icon <?php echo $ICON_FOLDER_CLASS ?>"></td>
                        <td><?php echo $item; ?></td>
                        <td><?php echo date("Y/m/d H:i", filemtime($itemPath)) ?></td>
                        <td><?php echo count(scandir($itemPath)) - 2; ?></td>
                    </tr>
                    <?php $i++; ?>
                <? endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if(count($items["files"]) > 0) : ?>
        <div class="files section">
            <div class="section-title">Files</div>
            <table class="noselect">
                <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th data-sort="string-ins">Name</th>
                    <th data-sort="string-ins" style="width:20%;">Last modified</th>
                    <th data-sort="float" style="width:10%;">Size (mb)</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["files"] as $item => $itemPath): ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td onclick="window.open('<?php echo cleanURL($shareURL . $relativePath . "/" . $item); ?>')" class="icon <?php echo getFileExtensionClass($itemPath, $EXTENSIONS_CLASSES) ?>"></td>
                        <td onclick="window.open('<?php echo cleanURL($shareURL . $relativePath . "/" . $item); ?>')"><?php echo $item; ?></td>
                        <td onclick="window.open('<?php echo cleanURL($shareURL . $relativePath . "/" . $item); ?>')"><?php echo date("Y/m/d H:i", filemtime($itemPath)) ?></td>
                        <td onclick="window.open('<?php echo cleanURL($shareURL . $relativePath . "/" . $item); ?>')"><?php echo number_format(filesize($itemPath) / 1048576, 1); ?></td>
                    </tr>
                    <?php $i++; ?>
                <? endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>


<div class="footer"><?php echo $NAME; ?> - <?php echo $CREDITS; ?></div>

<script>
    $(document).ready(function () {
        window.name = "_share_<?php echo $shareID;?>";

        var clipboard = new ClipboardJS(".link");
        clipboard.on('success', function (e) {
            alert("Link " + e.text + " copied to clipboard")
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