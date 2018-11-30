<?php

/////////////////////////////////////////////////////////////////////////////
/// SETUP
/////////////////////////////////////////////////////////////////////////////
$rootFolder = realpath(__DIR__ . "/..");
$docRoot = getDocRoot();
$rootURL = getRootURL();
$baseURL = getBaseURL($docRoot);
$currentPage = getCurrentPage($rootFolder, $baseURL);
$currentPath = preg_replace('#/+#', '/', $rootFolder . $currentPage);
$currentURL = getCurrentURL();
$currentURLWithoutURI = getCurrentURLWithoutURI();
$alerts = [];
$wantAdmin = false;
$items = [];


/////////////////////////////////////////////////////////////////////////////
/// ADMIN
/////////////////////////////////////////////////////////////////////////////
if($currentPage == "/admin") header("Location: " . $baseURL . "?admin");
if(isset($_GET["noadmin"])) setAdminPassword("--delog");
if(isset($_POST["admin-password-submit"])) setAdminPassword($_POST["password"]);
$isAdmin = getAdminPassword() == $ADMIN_PASSWORD;
if(isset($_POST["admin-password-submit"]) && !$isAdmin) array_push($alerts, ["Can't authenticate as admin", "Can't authenticate as admin. The password you provided is incorrect."]);
if(isset($_GET["admin"]) && !$isAdmin) $wantAdmin = true;

/////////////////////////////////////////////////////////////////////////////
/// DOWNLOADS
/////////////////////////////////////////////////////////////////////////////
if(isset($_GET["download"]))
{
    list($success, $hint) = zipFileAndDownload($rootFolder, $currentPath, $FORBIDEN_ITEMS, $MAX_ZIP_SIZE_IN_MB, $TMP_FOLDER);
    if(!$success) array_push($alerts, ["Can't zip folder", "Can't zip folder " . $currentPage . "<br/>Hint : " . $hint]);
}

/////////////////////////////////////////////////////////////////////////////
/// FILES AND FOLDERS
/////////////////////////////////////////////////////////////////////////////
if(isset($_POST["password-submit"])) setPassword($rootFolder, $currentPath, $_POST["password"]);
list($isProtected, $requiredPasswords, $savedPassword, $isAuthorized) = isAuthorized($rootFolder, $currentPath);
if($isAdmin) $isAuthorized = true;
$listingAllowed = !listingForbidden($currentPath);
$downloadAllowed = !downloadForbidden($currentPath);
$shownAllowed = !showForbidden($currentPath);
//if($TRACKING_ENABLED && $isProtected && !$isAdmin) trackItem($rootFolder, $currentPath, $isAuthorized, $savedPassword);
if($TRACKING_ENABLED && !$isAdmin) trackItem($rootFolder, $currentPath, $isAuthorized, $savedPassword);
if($isAuthorized)
{
    if(!file_exists($currentPath)) array_push($alerts, ["File not found", "The file " . $currentPage . " does not exist."]);
    else
    {
        if(is_file($currentPath))
        {
            if(!displayFile($currentPath, $FORBIDEN_ITEMS)) array_push($alerts, ["File not found", "The file " . $currentPage . " does not exist."]);
        }
        else
        {
            $items = scanFolder($currentPath, $FORBIDEN_ITEMS, $isAdmin);
            $readmeContent = getReadme($currentPath, false);
        }
    }
}

if(!$isAdmin && $isAuthorized && !$listingAllowed) array_push($alerts, ["Can't list folder", "You are not allowed to list this folder contents."]);
if($isAdmin)
{
    $subAlerts = [];
    if($isProtected && count($requiredPasswords) > 1) array_push($subAlerts, "Password protected, see passwords below");
    if($isProtected && count($requiredPasswords) == 1) array_push($subAlerts, "Password protected: " . $requiredPasswords[0]);
    if(!$listingAllowed) array_push($subAlerts, "Listing not allowed for non admin users");
    if(!$shownAllowed) array_push($subAlerts, "Forlder not shown for non admin users");
    if(!$downloadAllowed) array_push($subAlerts, "Folder not downloadble");
    if(count($subAlerts) > 0) array_push($alerts, ["Special folder", implode("<br/>", $subAlerts)]);
}
$listingAllowed = $listingAllowed || $isAdmin;
$shownAllowed = $shownAllowed || $isAdmin;

/////////////////////////////////////////////////////////////////////////////
/// ADD PASSWORD
/////////////////////////////////////////////////////////////////////////////
if($isAdmin && isset($_POST["add-password-submit"]))
{
    $addpassword = clean($_POST["new-password"]);
    if($addpassword == "") array_push($alerts, ["Can't add password", "Password is not valid"]);
    elseif(in_array($addpassword, $requiredPasswords))
    {
        array_push($alerts, ["Can't add password", "Password already exists"]);
    }
    else
    {
        addNewPassword($currentPath, $addpassword);
        list($a, $requiredPasswords, $a, $a) = isAuthorized($rootFolder, $currentPath);
        array_push($alerts, ["Password added", "The password " . $addpassword . " has been added to the folder"]);
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
    <a href="<?php echo $baseURL; ?>">
        <div class="logo"></div>
    </a>
</div>

<div class="name"><a href="<?php echo $baseURL; ?>"><?php echo $NAME; ?></a></div>

<div class="navigation section">
    <div class="parent" data-toggle="tooltip" title="Go to parent folder"><?php if($currentPage != "/"): ?><a href="<?php echo cleanURL($baseURL . $currentPage . "/.."); ?>"><i class="icon <?php echo $ICON_PARENT_FOLDER_CLASS; ?>"></i></a><?php else: ?>.<?php endif; ?></div>
    <?php if($downloadAllowed): ?>
        <div id="download" data-toggle="tooltip" title="Download folder"><a href="<?php echo $currentURLWithoutURI . "?download" ?>"><i class="icon <?php echo $ICON_DOWNLOAD_FOLDER_CLASS; ?>"></i></a></div>
    <?php endif; ?>
    <?php if($isAdmin): ?>
        <div class="sep">|</div>
        <div data-toggle="tooltip" title="Leave admin mode"><a href="<?php echo $baseURL . "?noadmin"; ?>"><i class="icon <?php echo $ICON_LEAVE_ADMIN_CLASS; ?>"></i></a></div>
        <?php if($SHARING_ENABLED): ?>
            <div class="shares" data-toggle="tooltip" title="Shares management"><a href="<?php echo $baseURL . "shares"; ?>" target="_shares"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a></div>
        <?php endif; ?>
        <?php if($TRACKING_ENABLED): ?>
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

<?php if($isAdmin and $isProtected): ?>
    <?php sort($requiredPasswords); ?>
    <div class="passwords section">
        <div class="section-title">Add a password</div>
        <form method="post" class="inline">
            <input type="text" name="new-password" placeholder="password to add"/>
            <label></label><input type="submit" name="add-password-submit" value="Add password" style="width:150px;"/>
        </form>
    </div>
    <?php if(count($requiredPasswords) > 1): ?>
        <div class="passwords section">
            <div class="section-title">Passwords</div>
            <form method="post" class="inline">
                <input type="text" id="search-password" placeholder="search for (partial) password"/>
            </form>
            <div id="passwords">
                <?php foreach($requiredPasswords as $requiredPassword): ?>
                    <span data-password="<?php echo $requiredPassword; ?>"><?php echo $requiredPassword; ?> <a data-toggle="tooltip" title="Copy link + password" class="link" data-clipboard-text="<?php echo $currentURL . " (password: " . $requiredPassword . ")"; ?>"><i
                                    class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a></span>
                <?php endforeach; ?>
                <div id="no-found-passwords" style="display:none">No password found</div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if($wantAdmin && !$isAdmin): ?>
    <div class="authenticate section">
        <div class="section-title">Admin, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="Password"/>
            <input type="submit" name="admin-password-submit" value="Login"/>
        </form>
    </div>
<?php elseif(!$isAuthorized && !$wantAdmin): ?>
    <div class="authenticate section">
        <div class="section-title">Protected area, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="Password"/>
            <input type="submit" name="password-submit" value="Login"/>
        </form>
    </div>
<?php elseif(isset($items)): ?>
    <?php if($readmeContent != ""): ?>
        <div class="readme section">
            <div class="readme-content"><?php echo $readmeContent ?></div>
        </div>
    <?php endif; ?>

    <?php if(count($items["folders"]) > 0 && $listingAllowed): ?>
        <div class="folders section">
            <div class="section-title">Folders</div>
            <table class="noselect">
                <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th data-sort="string-ins">Name</th>
                    <th data-sort="string-ins" style="width:20%;">Last modified</th>
                    <th data-sort="int" style="width:10%;"># items</th>
                    <?php if($isAdmin): ?>
                        <th width="70">Actions</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["folders"] as $item => $itemPath): ?>
                    <?php list($itemProtected, $itemPasswords, $unused, $unused) = isAuthorized($rootFolder, $itemPath); ?>
                    <?php $folderURL = myurlencode(cleanURL($rootURL . $baseURL . $currentPage . "/" . $item)); ?>
                    <?php $shareURL = cleanURL($baseURL . "create-share=" . $currentPage . "/" . $item); ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td onclick="location.href='<?php echo $folderURL; ?>'" class="icon <?php echo $ICON_FOLDER_CLASS; ?> <?php if($itemProtected) echo "disabled"; ?>"></td>
                        <td onclick="location.href='<?php echo $folderURL; ?>'"><?php echo $item; ?></td>
                        <td onclick="location.href='<?php echo $folderURL; ?>'"><?php echo date("Y/m/d H:i", filemtime($itemPath)) ?></td>
                        <td onclick="location.href='<?php echo $folderURL; ?>'"><?php echo count(scandir($itemPath)) - 2; ?></td>
                        <?php if($isAdmin): ?>
                            <td>
                                <?php if(!$itemProtected): ?>
                                    <a data-toggle="tooltip" title="Copy link" class="link" data-clipboard-text="<?php echo $folderURL; ?>"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a>
                                <?php else: ?>
                                    <a data-toggle="tooltip" title="Copy link + password" class="link" data-clipboard-text="<?php echo $folderURL . " (password: " . $itemPasswords[0] . ")"; ?>"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a>
                                <?php endif; ?>
                                <?php if($SHARING_ENABLED): ?>
                                    <a data-toggle="tooltip" title="Create share" href="<?php echo $shareURL; ?>" target="_shares"><i class="icon <?php echo $ICON_SHARE_CLASS; ?>"></i></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php $i++; ?>
                <? endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if(count($items["files"]) > 0 && $listingAllowed) : ?>
        <div class="files section">
            <div class="section-title">Files</div>
            <table class="noselect">
                <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th data-sort="string-ins">Name</th>
                    <th data-sort="string-ins" style="width:20%;">Last modified</th>
                    <th data-sort="float" style="width:10%;">Size (mb)</th>
                    <?php if($isAdmin): ?>
                        <th width="70">Actions</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["files"] as $item => $itemPath): ?>
                    <?php list($itemProtected, $itemPasswords, $unused, $unused) = isAuthorized($rootFolder, $itemPath); ?>
                    <?php $itemURL = myurlencode(cleanURL($rootURL . $baseURL . $currentPage . "/" . $item)); ?>
                    <tr class="<?php if($i % 2 == 1) echo "even"; ?>">
                        <td onclick="window.open('<?php echo $itemURL; ?>')" class="icon <?php echo getFileExtensionClass($itemPath, $EXTENSIONS_CLASSES) ?> <?php if($itemProtected) echo "disabled"; ?>"></td>
                        <td onclick="window.open('<?php echo $itemURL; ?>')"><?php echo $item; ?></td>
                        <td onclick="window.open('<?php echo $itemURL; ?>')"><?php echo date("Y/m/d H:i", filemtime($itemPath)) ?></td>
                        <td onclick="window.open('<?php echo $itemURL; ?>')"><?php echo number_format(filesize($itemPath) / 1048576, 1); ?></td>
                        <?php if($isAdmin): ?>
                            <td>
                                <?php if(!$itemProtected): ?>
                                    <a data-toggle="tooltip" title="Copy link" class="link" data-clipboard-text="<?php echo $itemURL; ?>"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a>
                                <?php else: ?>
                                    <a data-toggle="tooltip" title="Copy link + password" class="link" data-clipboard-text="<?php echo $itemURL . " (password: " . $itemPasswords[0] . ")"; ?>"><i class="icon <?php echo $ICON_LINK_FOLDER_CLASS; ?>"></i></a>
                                <?php endif; ?>
                                <?php if($SHARING_ENABLED): ?>
                                    <a data-toggle="tooltip" title="Create share" href="<?php echo $baseURL . "create-share=" . $currentPage . "/" . $item; ?>" target="_shares"><i class="icon <?php echo $ICON_SHARE_CLASS; ?>"></i></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
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
        window.name = "_files";

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
        $("#search-password").keyup(function () {
            var passwordSearch = this.value;
            $("#passwords span").hide();
            $("#no-found-passwords").hide();
            var found = 0;
            $("#passwords span").each(function (i, a) {
                var potentialPassword = $(this).attr("data-password");
                if (potentialPassword.indexOf(passwordSearch) !== -1) {
                    $(this).show();
                    found++;
                }
            });
            if (found == 0) {$("#no-found-passwords").show();}

        });

    });
</script>

</body>
</html>