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
            if($do)
            {
                $this->addDirDo($location, $name);
            }
        }

        public function maxSizeReached()
        {
            return $this->cumulatedSize > $this->maxCumulatedSize;
        }

        private function addDirDo($location, $name)
        {
            $name .= "/";
            $location .= "/";
            list($isProtected, $requiredPassword, $isAuthorized) = isAuthorized($this->rootPath, $location);
            if(!$isAuthorized || downloadForbidden($location))
            {
                return;
            }
            $dir = opendir($location);
            while($file = readdir($dir))
            {
                if($file == "." || $file == "..") continue;
                if(inArrayString($file, $this->forbiddenItems)) continue;
                $filePath = $location . $file;
                $isDir = filetype($filePath) == "dir";
                if($isDir)
                {
                    list($isProtected, $requiredPassword, $isAuthorized) = isAuthorized($this->rootPath, $filePath);
                    if(!$isAuthorized || downloadForbidden($filePath))
                    {
                        //$this->addDir($filePath, $name . $file . "-not-authorized", false);
                        continue;
                    }
                }
                else
                {
                    $this->cumulatedSize += filesize($filePath);
                    if($this->maxSizeReached())
                    {
                        return;
                    }
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
        @mkdir($zipFolder);
        if(!is_dir($zipFolder))
        {
            throw new Exception("Can't write zip file");
        }
        $za = new FlxZipArchive($rootPath, $forbiddenItems, $sizeLimitInMB * 1e6);
        $res = $za->open($zipFile, ZipArchive::CREATE);
        if($res !== true)
        {
            throw new Exception("Can't write zip file");
        }
        $za->addDir($path, $folderName);
        if($za->maxSizeReached())
        {
            throw new Exception("Max size of " . $sizeLimitInMB . "MB reached");
        }
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
    setcookie("admin-password", $password, 0);
    $GLOBALS["admin-password"] = $password;
}

///////////////////////////////////////////////////////////////////////////////
function getAdminPassword()
{
    if(isset($GLOBALS["admin-password"]))
    {
        return $GLOBALS["admin-password"];
    }
    else if(isset($_COOKIE["admin-password"]))
    {
        return $_COOKIE["admin-password"];
    }
    else
    {
        return "";
    }
}


///////////////////////////////////////////////////////////////////////////////
function setPassword($rootPath, $path, $password)
{
    $lowerProtectedPath = getLowerProtectedPath($rootPath, $path);
    if($lowerProtectedPath !== false)
    {
        $lowerProtectedPath = cleanPathForCookie($lowerProtectedPath);
        setcookie($lowerProtectedPath, $password, 0);
        $GLOBALS["password-" . $lowerProtectedPath] = $password;
    }
}

///////////////////////////////////////////////////////////////////////////////
function getPassword($lowerProtectedPath)
{
    $lowerProtectedPath = cleanPathForCookie($lowerProtectedPath);
    if(isset($GLOBALS["password-" . $lowerProtectedPath]))
    {
        return $GLOBALS["password-" . $lowerProtectedPath];
    }
    else if(isset($_COOKIE[$lowerProtectedPath]))
    {
        return $_COOKIE[$lowerProtectedPath];
    }
    else
    {
        return null;
    }
}

///////////////////////////////////////////////////////////////////////////////
function cleanPathForCookie($path)
{
    $path = clean($path);
    if($path == "")
    {
        $path = "-";
    }
    return $path;
}

///////////////////////////////////////////////////////////////////////////////
function listingForbidden($path)
{
    return file_exists($path . "/nolist");
}


///////////////////////////////////////////////////////////////////////////////
function showForbidden($path)
{
    return file_exists($path . "/noshow");
}

///////////////////////////////////////////////////////////////////////////////
function downloadForbidden($path)
{
    return file_exists($path . "/nodownload") || showForbidden($path) || listingForbidden($path);
}

///////////////////////////////////////////////////////////////////////////////
function isAuthorized($rootPath, $path)
{
    $lowerProtectedPath = getLowerProtectedPath($rootPath, $path);
    if($lowerProtectedPath === false)
    {
        return [false, "", true];
    }
    $requiredPassword = file_get_contents($rootPath . "/" . $lowerProtectedPath . "/password");
    $savedPassword = getPassword($lowerProtectedPath);
    return [true, $requiredPassword, $savedPassword == $requiredPassword];
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
        if($currentPath == "")
        {
            $currentPath = "/";
        }
        $currentRealPath = realpath($rootPath . "/" . $currentPath);
        if(file_exists($currentRealPath . "/password"))
        {
            $lowerPath = $currentRealPath;
            break;
        }
        if(file_exists($currentRealPath . "/nopassword"))
        {
            $lowerPath = false;
            break;
        }
    }
    if($lowerPath === false)
    {
        return false;
    }
    return str_replace($rootPath, "", $lowerPath);
}

///////////////////////////////////////////////////////////////////////////////
function getCurrentPage($rootFolder, $baseURL)
{
    $currentPage = get($_GET["__page__"], "/");
    if(strContains($rootFolder, $currentPage))
    {
        header("Location: " . $baseURL . "/");
    }
    $currentPage .= "/";
    $currentPage = rtrim($currentPage, "/");
    if($currentPage == "")
    {
        $currentPage = "/";
    }
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
    return rtrim(str_replace(["///", "//"], ["/", "/"], $url), "/");
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
function getReadme($folderPath, $offsetHeaders)
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
function displayFile($filePath)
{
    $fp = fopen($filePath, "rb");
    // header("Content-Description: File Transfer");
    // header("Content-Type: application/octet-stream");
    header("Content-Type: " . mime_content_type($filePath));
    header("Content-Length: " . filesize($filePath));
    fpassthru($fp);
    exit;
}

///////////////////////////////////////////////////////////////////////////////
function scanFolder($folderPath, $forbiddenItems)
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
        if(showForbidden($itemPath)) continue;
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
    }
    finally
    {
        fclose($file);
        return $data;
    }
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
    }
}

///////////////////////////////////////////////////////////////////////////////
function getShareAndDownload($rootPath, $sharesFolder, $shareID)
{
    try
    {
        $sharesPath = $rootPath . "/" . $sharesFolder;
        $share = getShare($sharesPath, $shareID);
        if($share == null) return false;
        $file = $rootPath . "/" . $share->file;
        if(!file_exists($file)) return false;
        $share->views += 1;
        saveShare($sharesPath, $shareID, $share);
        displayFile($file);
        return true;
    }
    catch(Exception $e)
    {
        return false;
    }
}

?>