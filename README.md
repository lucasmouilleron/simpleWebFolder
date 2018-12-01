# SimpleWebFolder

A simple web folder.
For Apache web servers.

![Screenshot](http://grabs.lucasmouilleron.com/Screen%20Shot%202018-04-27%20at%2012.37.00.png)

## Features
- List files and folders
- Nice layout
- Password protection: 
    - drop a `.password` file containing the deisred password in folders (and subfolders) you want to protect
    - drop a `.nopassword` file in folders (and subfolders) you want to deprotect (in case of protected parent)
- Download folder as a zip
- Shares: expiration, path obfuscation, tracking
- `README.md` files in folders are interpreted and displayed on top  


## Install
- Requires PHP 5+, Apache 2.4+
- `cd _sf;composer install`
- Drop the `_sf` folder in the root folder
- Drop the `_sf_assets` folder in the root folder
- Create the `_sf_shares` folders for shares to be activated
- Drop the `.htaccess` file in the root folder

## Customisation
- Don't edit files in `/_sf`
- Create `/_sf_overrides` folder
    - Create `config.php` and override configs defined in `_sf/config.php`
    - Create `style.css` and override styles
    
## TODO
- Clean shares