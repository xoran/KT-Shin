Bronco's Pinterest version

New in this version:

- Added multi language support 
  - add your own in inc/languages.php. 
  - change language in the inc/configuration.php (no time to add it to config page)
  - to add messages to a template use {"message in english"|e} and add a line to languages.php in the $massages array: 'message in english'=>'message en français', (caution: the array's key is always lowercase event if message in template is not !)

- responsive pinterest aspect (widgets columns)
	- uses css3 column property & media queries
	- added possibility to specify an img url for the links (as an illustration)

- added a "notebook" mode to write or edit a note (easy and fast) (see tools)
	- new bookmarklet 
	- new favlink to go directly into the notebook part
	- markdown (not only for notebook part). Uses https://github.com/broncowdd/parsedown (see in inc/functions.php)

- Some changes in the tag cloud page
  - the tag's size is changed with a class and no longer with an inline style
  - you can target the sizes like you want; if a tag is used only one time, its class is size0, so you can hide it from shaarli.css
  - the admin can now delete a tag directly from the tag cloud. This feature uses the same security system used by SebSauvage (token)
    (if not logged, the delete button doesn't appears)

- Added the higlighting of search terms

- Ddded body css clases to easily target every page and every tag result page:
    - .addlink
    - .addnote
    - .changepassword
    - .changetag
    - .configure
    - .daily
    - .editlink
    - .login
    - .picwall
- Cleaned some inline css rules and replaced them into shaarli.css

- Structural changes:
  I cuted the index file into several parts to make easier the futures changes and the futures updates 
	Now, there is 
	- inc/configuration.php
	- inc/functions.php
	- inc/initialisation.php

	those files are here to add some features to shaarli without important changes in the index.php file
	- inc/private_get_commands.php
	- inc/public_get_commands.php
	- inc/personal_post_actions.php
	- inc/render_basic_page.php


Caution ! I clearly don't give a shit of IE... this version is NOT specificly designed for this "browser"
I've tested it on Firefox, Chromium  & opera...
Tell me if you see something wrong ;-)




![Shaarli logo](http://sebsauvage.net/wiki/lib/exe/fetch.php?media=php:php_shaarli:php_shaarli_logo_inkscape_w600_transp-nq8.png)

Shaarli, the personal, minimalist, super-fast, no-database delicious clone.

You want to share the links you discover ? Shaarli is a minimalist delicious clone you can install on your own website.
It is designed to be personal (single-user), fast and handy. 


Features:

 * Minimalist design (simple is beautiful)
 * **FAST**
 * Dead-simple installation: Drop the files, open the page. No database required.
 * Easy to use: Single button in your browser to bookmark a page
 * Save url, title, description (unlimited size). Classify links with tags (with autocomplete)
 * Tag renaming, merging and deletion.
 * Automatic thumbnails for various services (imgur, imageshack.us, flickr, youtube, vimeo, dailymotion…)
 * Automatic conversion of URLs to clickable links in descriptions. Support for http/ftp/file/apt/magnet protocols.
 * Save links as public or private
 * 1-clic access to your private links/notes
 * Browse links by page, filter by tag or use the full text search engine
 * Permalinks (with QR-Code) for easy reference
 * RSS and ATOM feeds (which can be filtered by tag or text search)
 * Tag cloud
 * Picture wall (which can be filtered by tag or text search)
 * “Links of the day” Newspaper-like digest, browsable by day.
 * “Daily” RSS feed: Get each day a digest of all new links.
 * [PubSubHubbub](https://code.google.com/p/pubsubhubbub/) protocol support
 * Easy backup (Data stored in a single file)
 * Compact storage (1315 links stored in 150 kb)
 * Mobile browsers support
 * Also works with javascript disabled
 * Can import/export Netscape bookmarks (for import/export from/to Firefox, Opera, Chrome, Delicious…)
 * Brute force protected login form
 * Protected against [XSRF](http://en.wikipedia.org/wiki/Cross-site_request_forgery), session cookie hijacking.
 * Automatic removal of annoying FeedBurner/Google FeedProxy parameters in URL (?utm_source…)
 * Shaarli is a bookmarking application, but you can use it for micro-blogging (like Twitter), a pastebin, an online notepad, a snippet repository, etc.
 * You will be automatically notified by a discreet popup if a new version is available
 * Pages are easy to customize (using CSS and simple RainTPL templates)


Requires php 5.1

More information on the project page:
http://sebsauvage.net/wiki/doku.php?id=php:shaarli

------------------------------------------------------------------------------

Shaarli is distributed under the zlib/libpng License:

Copyright (c) 2011 Sébastien SAUVAGE (sebsauvage.net)

This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from
the use of this software.

Permission is granted to anyone to use this software for any purpose,
including commercial applications, and to alter it and redistribute it 
freely, subject to the following restrictions:

  1. The origin of this software must not be misrepresented; you must not 
     claim that you wrote the original software. If you use this software
     in a product, an acknowledgment in the product documentation would
     be appreciated but is not required.

  2. Altered source versions must be plainly marked as such, and must
     not be misrepresented as being the original software.

  3. This notice may not be removed or altered from any source distribution.

------------------------------------------------------------------------------
