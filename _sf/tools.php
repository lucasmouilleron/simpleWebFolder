<?php

///////////////////////////////////////////////////////////////////////////////
require_once __DIR__ . "/vendors/autoload.php";

///////////////////////////////////////////////////////////////////////////////
date_default_timezone_set($TIMEZONE);


///////////////////////////////////////////////////////////////////////////////
function zipFileAndDownload($rootPath, $path, $forbiddenItems, $sizeLimitInMB, $tmpFolder)
{

    class FlxZipArchive extends ZipArchive
    {
        private $rootPath;
        private $cumulatedSize = 0;
        private $maxCumulatedSize = 0;
        private $forbiddenItems = [];

        public function __construct($rootPath, $forbiddenItems, $maxCumulatedSize)
        {
            $this->rootPath = $rootPath;
            $this->maxCumulatedSize = $maxCumulatedSize;
            $this->forbiddenItems = $forbiddenItems;
        }

        public function addDir($location, $name, $do = true)
        {
            $this->addEmptyDir($name);
            if($do) $this->addDirDo($location, $name);
        }

        public function maxSizeReached()
        {
            return $this->cumulatedSize > $this->maxCumulatedSize;
        }

        private function addDirDo($location, $name)
        {
            $name .= "/";
            $location .= "/";
//            if(!file_exists($this->rootPath . "/" . $location)) return;
            list($isProtected, $requiredPasswords, $savedPassword, $isAuthorized) = isAuthorized($this->rootPath, $location);
            if(!$isAuthorized || downloadForbidden($location)) return;
            $dir = opendir($location);
            while($file = readdir($dir))
            {
                if($file == "." || $file == "..") continue;
                if(inArrayString($file, $this->forbiddenItems)) continue;
                $filePath = $location . $file;
                $isDir = filetype($filePath) == "dir";
                if($isDir)
                {
                    list($isProtected, $requiredPasswords, $savedPassword, $isAuthorized) = isAuthorized($this->rootPath, $filePath);
                    if(!$isAuthorized || downloadForbidden($filePath))
                    {
                        //$this->addDir($filePath, $name . $file . "-not-authorized", false);
                        continue;
                    }
                }
                else
                {
                    $this->cumulatedSize += filesize($filePath);
                    if($this->maxSizeReached()) return;
                }
                $do = $isDir ? "addDir" : "addFile";
                $this->$do($location . $file, $name . $file);
            }
        }
    }

    $success = false;
    $hint = "no hint";
    $folderName = basename($path);
    $zipFolder = $tmpFolder . "/zip-download-" . uniqid();
    $zipFile = $zipFolder . "/" . $folderName . ".zip";
    try
    {
        if(!file_exists($path)) throw new Exception("Target does not exist");
        @mkdir($zipFolder);
        if(!is_dir($zipFolder)) throw new Exception("Can't write zip file");
        $za = new FlxZipArchive($rootPath, $forbiddenItems, $sizeLimitInMB * 1e6);
        $res = $za->open($zipFile, ZipArchive::CREATE);
        if($res !== true) throw new Exception("Can't write zip file");
        $za->addDir($path, $folderName);
        if($za->maxSizeReached()) throw new Exception("Max size of " . $sizeLimitInMB . "MB reached");
        $za->close();
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=" . basename($zipFile));
        header("Content-Transfer-Encoding: binary");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: public");
        header("Content-Length: " . filesize($zipFile));
        ob_clean();
        flush();
        readfile($zipFile);
        $success = true;
    }
    catch(Exception $e)
    {
        $hint = $e->getMessage();
    }
    finally
    {
        @unlink($zipFile);
        @rmdir($zipFolder);
    }
    return [$success, $hint];
}

///////////////////////////////////////////////////////////////////////////////
function setAdminPassword($password)
{
    setcookie("admin-password", $password, 0, "/");
    $GLOBALS["admin-password"] = $password;
}

///////////////////////////////////////////////////////////////////////////////
function getAdminPassword()
{
    if(isset($GLOBALS["admin-password"])) return $GLOBALS["admin-password"];
    else if(isset($_COOKIE["admin-password"])) return $_COOKIE["admin-password"];
    else return "";
}


///////////////////////////////////////////////////////////////////////////////
function setPassword($rootPath, $path, $password)
{
    $lowerProtectedPath = getLowerProtectedPath($rootPath, $path);
    if($lowerProtectedPath !== false)
    {
        $lowerProtectedPath = cleanPathForCookie($lowerProtectedPath);
        setcookie($lowerProtectedPath, $password, 0, "/");
        $GLOBALS["password-" . $lowerProtectedPath] = $password;
    }
}

///////////////////////////////////////////////////////////////////////////////
function getPassword($lowerProtectedPath)
{
    $lowerProtectedPath = cleanPathForCookie($lowerProtectedPath);
    if(isset($GLOBALS["password-" . $lowerProtectedPath])) return $GLOBALS["password-" . $lowerProtectedPath];
    else if(isset($_COOKIE[$lowerProtectedPath])) return $_COOKIE[$lowerProtectedPath];
    else return null;
}

///////////////////////////////////////////////////////////////////////////////
function cleanPathForCookie($path)
{
    $path = clean($path);
    if($path == "") $path = "-";
    return $path;
}

///////////////////////////////////////////////////////////////////////////////
function listingForbidden($path)
{
    return file_exists($path . "/.nolist");
}


///////////////////////////////////////////////////////////////////////////////
function showForbidden($path)
{
    return file_exists($path . "/.noshow");
}

///////////////////////////////////////////////////////////////////////////////
function downloadForbidden($path)
{
    return file_exists($path . "/.nodownload") || showForbidden($path) || listingForbidden($path);
}

///////////////////////////////////////////////////////////////////////////////
function isAuthorized($rootPath, $path)
{
    $lowerProtectedPath = getLowerProtectedPath($rootPath, $path);
    if($lowerProtectedPath === false) return [false, "", "", true];
    $requiredPasswords = explode("\n", file_get_contents($rootPath . "/" . $lowerProtectedPath . "/.password"));
    $savedPassword = getPassword($lowerProtectedPath);
    return [true, $requiredPasswords, $savedPassword, in_array($savedPassword, $requiredPasswords)];
}


///////////////////////////////////////////////////////////////////////////////
function getLowerProtectedPath($rootPath, $path)
{
    $relativePath = cleanURL("/" . str_replace($rootPath, "", $path));
    $lowerPath = false;
    $paths = explode("/", $relativePath);
    for($i = 0; $i < count($paths); $i++)
    {
        $currentPath = implode("/", array_slice($paths, 0, count($paths) - $i));
        if($currentPath == "") $currentPath = "/";
        $currentRealPath = realpath($rootPath . "/" . $currentPath);
        if(file_exists($currentRealPath . "/.password"))
        {
            $lowerPath = $currentRealPath;
            break;
        }
        if(file_exists($currentRealPath . "/.nopassword"))
        {
            $lowerPath = false;
            break;
        }
    }
    if($lowerPath === false) return false;
    return str_replace($rootPath, "", $lowerPath);
}

///////////////////////////////////////////////////////////////////////////////
function getCurrentPage($rootFolder, $baseURL)
{
    $currentPage = get($_GET["__page__"], "/");
    if(strContains($rootFolder, $currentPage)) header("Location: " . $baseURL . "/");
    $currentPage .= "/";
    $currentPage = rtrim($currentPage, "/");
    if($currentPage == "") $currentPage = "/";
    return $currentPage;
}

///////////////////////////////////////////////////////////////////////////////
function getCurrentURL()
{
    return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
}

///////////////////////////////////////////////////////////////////////////////
function getCurrentURLWithoutURI()
{
    return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . explode('?', $_SERVER['REQUEST_URI'], 2)[0];
}

///////////////////////////////////////////////////////////////////////////////
function getDocRoot()
{
    return preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']); # ex: /var/www
}

///////////////////////////////////////////////////////////////////////////////
function getRootURL()
{
    return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];
}

///////////////////////////////////////////////////////////////////////////////
function getBaseURL($docRoot)
{
    return str_replace("/_sf", "", preg_replace("!^${docRoot}!", '', __DIR__)) . "/";
}

///////////////////////////////////////////////////////////////////////////////
function inArrayString($needle, $haystack)
{
    foreach($haystack as $h)
    {
        if($needle == $h) return true;
    }
    return false;
}

///////////////////////////////////////////////////////////////////////////////
function getFileExtensionClass($filePath, $map)
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if(array_key_exists($ext, $map)) return $map[$ext];
    if(array_key_exists("default", $map)) return $map["default"];
    return "default";
}

///////////////////////////////////////////////////////////////////////////////
function cleanURL($url)
{

    return str_replace(":__", "://", rtrim(str_replace(["///", "//"], ["/", "/"], str_replace("://", ":__", $url)), "/"));
}

///////////////////////////////////////////////////////////////////////////////
function clean($string)
{
    $string = str_replace(' ', '-', $string);
    return preg_replace('/[^A-Za-z0-9\-]/', '-', $string);
}

///////////////////////////////////////////////////////////////////////////////
function strContains($needle, $haystack)
{
    return strpos($haystack, $needle) !== false;
}

///////////////////////////////////////////////////////////////////////////////
function get(&$var, $default = null)
{
    return isset($var) ? $var : $default;
}

///////////////////////////////////////////////////////////////////////////////
function getReadme($folderPath, $offsetHeaders=false)
{
    $readmeContent = "";
    $file = $folderPath . "/README.md";
    if(file_exists($file))
    {
        $p = new Parsedown();
        $readmeContent = $p->text(fread(fopen($file, "r"), filesize($file)));
        if($offsetHeaders) $readmeContent = offsetHeaders($readmeContent);
    }
    return $readmeContent;
}

///////////////////////////////////////////////////////////////////////////////
function displayFile($filePath, $forbiddenItems)
{
    if(in_array(basename($filePath), $forbiddenItems)) return false;
    $fp = fopen($filePath, "rb");
    // header("Content-Description: File Transfer");
    // header("Content-Type: application/octet-stream");
    header("Content-Type: " . mime_content_type($filePath));
    header("Content-Length: " . filesize($filePath));
    fpassthru($fp);
    exit;
}

///////////////////////////////////////////////////////////////////////////////
function scanFolder($folderPath, $forbiddenItems, $isAdmin)
{
    $items = scandir($folderPath);
    $filesMap = array();
    $foldersMap = array();
    foreach($items as $item)
    {
        $itemPath = preg_replace('/(\/+)/', '/', $folderPath . "/" . $item);
        if(filesize($itemPath) == 0)
        {
            // continue;
        }
        if(inArrayString($item, $forbiddenItems)) continue;
        if(!$isAdmin && showForbidden($itemPath)) continue;
        if(is_dir($itemPath)) $foldersMap[$item] = $itemPath;
        else $filesMap[$item] = $itemPath;
    }
    return array("files" => $filesMap, "folders" => $foldersMap);
}

///////////////////////////////////////////////////////////////////////////////
function offsetHeaders($content)
{
    return str_replace("<h2", "<h3", str_replace("<h3", "<h4", str_replace("<h4", "<h5", str_replace("<h5", "<h6", preg_replace('@<h1[^>]*?>.*?<\/h1>@si', '', $content)))));
}

///////////////////////////////////////////////////////////////////////////////
function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

///////////////////////////////////////////////////////////////////////////////
function getShares($sharesFolder, $max = 99)
{
    $items = scandir($sharesFolder);
    $shares = [];
    foreach($items as $item)
    {
        $shareID = basename($item);
        if($shareID == "." || $shareID == ".." || startsWith($shareID, ".")) continue;
        $share = getShare($sharesFolder, $shareID);
        $share->ID = $shareID;
        array_push($shares, $share);
    }
    function shareSort($u, $v)
    {
        return $v->creation - $u->creation;
    }

    usort($shares, "shareSort");
    return array_slice($shares, 0, $max);
}

///////////////////////////////////////////////////////////////////////////////
function getShare($sharesFolder, $shareID)
{
    $sharePath = $sharesFolder . "/" . $shareID;
    if(!file_exists($sharePath))
    {
        return null;
    }
    $data = null;
    $file = fopen($sharePath, "r");
    try
    {
        flock($file, LOCK_SH);
        $data = json_decode(fread($file, filesize($sharePath)));
        $data->ID = $shareID;
    }
    finally
    {
        fclose($file);
        return $data;
    }
}

///////////////////////////////////////////////////////////////////////////////
function createShare($sharesFolder, $shareID, $file, $durationInDays, $password)
{
    $share = new stdClass();
    $share->ID = $shareID;
    $share->file = $file;
    $share->views = [];
    $share->creation = time();
    $share->duration = $durationInDays * 24 * 60 * 60;
    $share->password = $password;
    return saveShare($sharesFolder, $shareID, $share);
}

///////////////////////////////////////////////////////////////////////////////
function saveShare($sharesFolder, $shareID, $share)
{
    $sharePath = $sharesFolder . "/" . $shareID;
    $file = fopen($sharePath, "w+");
    try
    {
        flock($file, LOCK_EX);
        fwrite($file, json_encode($share));
    }
    finally
    {
        fclose($file);
        return $share;
    }
}

///////////////////////////////////////////////////////////////////////////////
function removeShare($sharesFolder, $shareID)
{
    @unlink($sharesFolder . "/" . $shareID);
}


///////////////////////////////////////////////////////////////////////////////
function getShareAsUser($rootPath, $sharesFolder, $shareID, $subPath = null, $maxViews = 500)
{
    try
    {
        $share = getShare($sharesFolder, $shareID);
        if($share == null) return [false, "share does not exist", true, null];
        if(!file_exists($rootPath . "/" . $share->file)) return [false, "share does not exist anymore", true, null];
        if($share->duration == "") $share->duration = 0;
        if($share->duration > 0 && time() - $share->creation > $share->duration) return [false, "share has expired", true, null];
        $view = new stdClass();
        $view->ip = getRealIpAddr();
        $view->date = time();
        $viewFile = $share->file;
        if(isset($subPath)) $viewFile = $viewFile . $subPath;
        $view->item = $viewFile;
        array_push($share->views, $view);
        if(count($share->views) > $maxViews) array_shift($share->views);
        saveShare($sharesFolder, $shareID, $share);
        if(isShareAuthorized($share)) return [true, "OK", true, $share];
        else return [false, "Password is required", false, null];
    }
    catch(Exception $e)
    {
        return [false, $e, true, null];
    }
}

///////////////////////////////////////////////////////////////////////////////
function isShareAuthorized($share)
{
    if($share->password == null || $share->password == "") return true;
    return $share->password == getPasswordShare($share->ID);
}


///////////////////////////////////////////////////////////////////////////////
function setPasswordShare($shareID, $password)
{
    setcookie($shareID, $password, 0, "/");
    $GLOBALS["password-share-" . $shareID] = $password;
}

///////////////////////////////////////////////////////////////////////////////
function getPasswordShare($shareID)
{
    if(isset($GLOBALS["password-share-" . $shareID])) return $GLOBALS["password-share-" . $shareID];
    else if(isset($_COOKIE[$shareID])) return $_COOKIE[$shareID];
    else return null;
}


///////////////////////////////////////////////////////////////////////////////
function getShareExpirationString($share)
{
    if($share->duration == "") $share->duration = 0;
    if($share->duration == 0) return "Never";
    $expiration = $share->duration - (time() - $share->creation);
    if($expiration < 0) return "Expired";
    return date("Ymd @ H:i", time() + $expiration);
}

///////////////////////////////////////////////////////////////////////////////
function getRealIpAddr()
{
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

/////////////////////////////////////////////////////////////////////////////
function getTrackings($roothPath, $password = null, $item = null, $maxItems = null)
{
    $trackingFile = $roothPath . "/.tracking";
    if(!file_exists($trackingFile)) return [];
    $trackingsRaw = file($trackingFile);
    $trackings = [];
    array_shift($trackingsRaw);
    if(!isset($maxItems) || $maxItems == "all") $maxItems = count($trackingsRaw);
    foreach($trackingsRaw as $trackingRaw)
    {
        $trackingRaw = str_getcsv($trackingRaw, ";");
        $tracking = new stdClass();
        $tracking->item = $trackingRaw[0];
        if(isset($item) && !strContains($item, $tracking->item)) continue;
        $tracking->authorized = $trackingRaw[1];
        $tracking->ip = $trackingRaw[3];
        $tracking->password = $trackingRaw[2];
        if(isset($password) && !strContains($password, $tracking->password)) continue;
        $tracking->date = $trackingRaw[4];
        array_push($trackings, $tracking);
    }
    $trackings = array_reverse($trackings);
    $trackings = array_slice($trackings, 0, $maxItems);
    return $trackings;
}

///////////////////////////////////////////////////////////////////////////////
function trackItem($rootPath, $path, $isAuthotirzed, $passwordProvided, $maxSizeInBytes = 3000)
{
    $trackFile = $rootPath . "/.tracking";
    $headers = ["path", "authorized", "password", "ip", "date"];
    try
    {
        $trackFileSize = @filesize($trackFile);
        if($trackFileSize > $maxSizeInBytes)
        {
            $lines = file($trackFile);
            $file = fopen($trackFile, "w");
            flock($file, LOCK_EX);
            $nbLines = count($lines);
            $offset = intval($nbLines / 2);
            if($offset > 0)
            {
                fputcsv($file, $headers, ";");
                foreach(array_slice($lines, $offset, $nbLines - $offset) as $line) fwrite($file, $line);
            }
            fclose($file);
        }
        $file = fopen($trackFile, "a");
        flock($file, LOCK_EX);
        if($trackFileSize == false || $trackFileSize == 0) fputcsv($file, $headers, ";");
        fputcsv($file, [str_replace($rootPath, "", $path), $isAuthotirzed ? "yes" : "no", $passwordProvided, getRealIpAddr(), time()], ";");

    }
    finally
    {
        if(isset($file)) @fclose($file);
    }
}

?>