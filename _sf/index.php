<?php


/////////////////////////////////////////////////////////////////////////////
if(file_exists(__DIR__ . "/config.php"))
{
    require __DIR__ . "/config.php";
}
if(file_exists(__DIR__ . "/../_sf_overrides/config.php"))
{
    require __DIR__ . "/../_sf_overrides/config.php";
}
require_once __DIR__ . "/tools.php";

/////////////////////////////////////////////////////////////////////////////
$rootFolder = realpath(__DIR__ . "/..");
$docRoot = getDocRoot();
$baseURL = getBaseURL($docRoot);
$currentPage = getCurrentPage($rootFolder, $baseURL);
$currentPath = realpath($rootFolder . $currentPage);
$currentURL = getCurrentURL();
$currentURLWithoutURI = getCurrentURLWithoutURI();
$alerts = [];
$wantAdmin = false;
/////////////////////////////////////////////////////////////////////////////
if(isset($_GET["download"]))
{
    list($success, $hint) = zipFileAndDownload($rootFolder, $currentPath, $FORBIDEN_ITEMS, $MAX_ZIP_SIZE_IN_MB, $TMP_FOLDER);
    if(!$success)
    {
        array_push($alerts, ["Can't zip folder", "Can't zip folder " . $currentPage . "<br/>Hint : " . $hint]);
    }
}
if(isset($_POST["password-submit"]))
{
    setPassword($rootFolder, $currentPath, $_POST["password"]);
}
if(isset($_POST["admin-password-submit"]))
{
    setAdminPassword($_POST["password"]);

}
$isAdmin = getAdminPassword() == $ADMIN_PASSWORD;
if(isset($_POST["admin-password-submit"]) && !$isAdmin) {
    array_push($alerts, ["Can't authenticate as admin", "Can't authenticate as admin. The password you provided is incorrect."]);
}
if(!array_key_exists("__page__", $_GET))
{
    header("Location: " . $baseURL);
}
if(isset($_GET["admin"]) && !$isAdmin)
{
    $wantAdmin = true;
}

list($isProtected, $requiredPassword, $isAuthorized) = isAuthorized($rootFolder, $currentPath);
if($isAdmin)
{
    $isAuthorized = true;
}
$listingAllowed = !listingForbidden($currentPath) || $isAdmin;
if(!$listingAllowed)
{
    array_push($alerts, ["Can't list folder", "You are not allowed to list this folder contents."]);
}
$downloadAllowed = !downloadForbidden($currentPath);
if($isAuthorized)
{
    $requiredPasswordDisplay = $requiredPassword;
    if(!file_exists($currentPath))
    {
        header("Location: " . $baseURL);
    }
    if(is_file($currentPath))
    {
        displayFile($currentPath);
    }

    $items = scanFolder($currentPath, $FORBIDEN_ITEMS);
    $readmeContent = getReadme($currentPath, true);
}
else
{
    $requiredPasswordDisplay = "- - - - - ";
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
    <a href="<?php echo $baseURL; ?>"><div class="logo"></div></a>
</div>

<div class="name"><a href="<?php echo $baseURL; ?>"><?php echo $NAME; ?></a></div>

<div class="navigation section">
    <div class="parent" data-toggle="tooltip" title="go to parent folder"><?php if($currentPage != "/"): ?><a href="<?php echo cleanURL($baseURL . $currentPage . "/.."); ?>"><i class="icon <?php echo $PARENT_FOLDER_CLASS; ?>"></i></a><?php endif; ?></div>
    <div id="link" data-clipboard-text="<?php echo $currentURL; ?>" data-toggle="tooltip" title="copy link<?php if($isProtected) echo " (protected folder)"; ?>"><i class="icon <?php echo $LINK_FOLDER_CLASS; ?>"></i></div>
    <?php if($downloadAllowed): ?>
        <div id="download" data-toggle="tooltip" title="download folder"><a href="<?php echo $currentURLWithoutURI . "?download" ?>"><i class="icon <?php echo $DOWNLOAD_FOLDER_CLASS; ?>"></i></a></div>
    <?php endif; ?>
    <div class="protected" data-toggle="tooltip" title="<?php if($isProtected) echo $requiredPasswordDisplay; else echo "no password needed"; ?>"><i class="icon <?php if($isProtected) echo $PROTECTED_FOLDER_CLASS; else echo $NON_PROTECTED_FOLDER_CLASS; ?>"></i></div>
    <div class="page"><?php echo $currentPage; ?></div>
</div>

<?php foreach($alerts as $alert): ?>
    <div class="alert">
        <h2><?php echo $alert[0]; ?></h2>
        <p><?php echo $alert[1]; ?></p>
    </div>
<?php endforeach; ?>

<?php if($wantAdmin): ?>
    <div class="authenticate section">
        <div class="section-title">Admin, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="password"/>
            <input type="submit" name="admin-password-submit" value="login"/>
        </form>
    </div>
<?php endif; ?>

<?php if(!$isAuthorized): ?>
    <div class="authenticate section">
        <div class="section-title">Protected area, please authenticate</div>
        <form method="post">
            <input type="password" name="password" placeholder="password"/>
            <input type="submit" name="password-submit" value="login"/>
        </form>
    </div>
<?php else: ?>

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
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["folders"] as $item => $itemPath): ?>
                    <tr onclick="location.href='<?php echo cleanURL($baseURL . $currentPage . "/" . $item); ?>'" class="<?php if($i % 2 == 1)
                    {
                        echo "even";
                    } ?>">
                        <td class="icon <?php echo $FOLDER_CLASS ?>"></td>
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
                </tr>
                </thead>
                <tbody>
                <?php $i = 0; ?>
                <?php foreach($items["files"] as $item => $itemPath): ?>
                    <tr onclick="window.open('<?php echo cleanURL($baseURL . $currentPage . "/" . $item); ?>')" class="<?php if($i % 2 == 1)
                    {
                        echo "even";
                    } ?>">
                        <td class="icon <?php echo getFileExtensionClass($itemPath, $EXTENSIONS_CLASSES) ?>"></td>
                        <td><?php echo $item; ?></td>
                        <td><?php echo date("Y/m/d H:i", filemtime($itemPath)) ?></td>
                        <td><?php echo number_format(filesize($itemPath) / 1048576, 1); ?></td>
                    </tr>
                    <?php $i++; ?>
                <? endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
    $(document).ready(function () {
        var clipboard = new ClipboardJS("#link");
        clipboard.on('success', function (e) {
            alert("Link copied to clipboard")
        });

        $('[data-toggle="tooltip"]').tooltipster({theme: "tooltipster-borderless", animationDuration: 200, delay: 20, side: "bottom"});
        var table = $("table").stupidtable();
        table.bind("aftertablesort", function (event, data) {
            var tableElt = data.$th.parent().parent().parent();
            tableElt.find("tr:even").addClass("even");
            tableElt.find("tr:odd").removeClass("even");
        });

        $("a").each(function () {
            var a = new RegExp("/" + window.location.host + "/");
            if (!a.test(this.href)) {
                $(this).click(function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.open(this.href, "_blank");
                });
            }
        });
    });
</script>

</body>
</html>